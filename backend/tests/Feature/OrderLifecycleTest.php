<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Recorre el ciclo completo PEDIDO -> PREPARACION -> REVISION -> DESPACHO
 * (Fase 1) tal como lo describe el Documento 3.
 */
class OrderLifecycleTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_full_order_lifecycle_reaches_dispatched(): void
    {
        $company = $this->makeCompany();
        $supervisor = $this->makeUser($company, Role::SUPERVISOR);
        $preparer = $this->makeUser($company, Role::PREPARADOR);
        $reviewer = $this->makeUser($company, Role::REVISOR);

        $customer = Customer::query()->create([
            'company_id' => $company->id, 'code' => 'CLI-1', 'name' => 'Cliente Uno', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id, 'sku' => 'SKU-1', 'name' => 'Producto Uno', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->create([
            'company_id' => $company->id, 'plate' => 'ABC-123', 'is_active' => true,
        ]);

        // 1. Supervisor crea el pedido.
        $create = $this->actingAs($supervisor, 'sanctum')->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity_requested' => 20, 'unit' => 'UNIDAD']],
        ])->assertCreated();

        $orderId = $create->json('data.id');
        $this->assertSame('PENDIENTE', $create->json('data.status'));

        // 2. Supervisor asigna al preparador.
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/assign", ['preparer_id' => $preparer->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'PREPARANDO');

        // 3. Preparador abre la preparación (se crea con cantidades en 0).
        $preparation = $this->actingAs($preparer, 'sanctum')
            ->getJson("/api/v1/orders/{$orderId}/preparation")
            ->assertOk();

        $orderItemId = $preparation->json('data.items.0.order_item_id');

        // 4. Preparador registra una cantidad MENOR sin motivo -> debe fallar.
        $this->actingAs($preparer, 'sanctum')->postJson("/api/v1/orders/{$orderId}/preparation/items", [
            'order_item_id' => $orderItemId,
            'quantity_prepared' => 15,
        ])->assertStatus(422);

        // 5. Con motivo, sí se acepta.
        $this->actingAs($preparer, 'sanctum')->postJson("/api/v1/orders/{$orderId}/preparation/items", [
            'order_item_id' => $orderItemId,
            'quantity_prepared' => 15,
            'difference_reason' => 'FALTANTE_INVENTARIO',
        ])->assertOk();
        $this->assertEquals(5, $this->actingAs($preparer, 'sanctum')
            ->getJson("/api/v1/orders/{$orderId}/preparation")->json('data.items.0.difference'));

        // 6. Finaliza preparación -> PREPARADO.
        $this->actingAs($preparer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/preparation/finish")
            ->assertOk();

        // 7. Otro preparador no puede tocar esta preparación (política por asignación).
        $otherPreparer = $this->makeUser($company, Role::PREPARADOR, ['email' => 'otro@'.$company->slug.'.test']);
        $this->actingAs($otherPreparer, 'sanctum')
            ->getJson("/api/v1/orders/{$orderId}/preparation")
            ->assertStatus(403);

        // El revisor debe poder ver los productos del pedido en la bandeja
        // de pendientes, no solo el conteo en 0 (bug real: el endpoint no
        // cargaba la relación items).
        $pending = $this->actingAs($reviewer, 'sanctum')->getJson('/api/v1/reviews/pending')->assertOk();
        $pendingOrder = collect($pending->json('data'))->firstWhere('id', $orderId);
        $this->assertNotEmpty($pendingOrder['items']);

        // 8. Revisor rechaza primero (debe exigir motivo).
        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/review/reject", [])
            ->assertStatus(422);

        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/review/reject", ['reason' => 'Falta ajustar cantidades'])
            ->assertCreated();

        // El pedido vuelve a PREPARANDO (Documento 3 §7) y el preparador puede corregir.
        $this->assertSame(
            'PREPARANDO',
            $this->actingAs($supervisor, 'sanctum')->getJson("/api/v1/orders/{$orderId}")->json('data.status')
        );

        // 9. Se finaliza de nuevo y esta vez se aprueba.
        $this->actingAs($preparer, 'sanctum')->postJson("/api/v1/orders/{$orderId}/preparation/finish")->assertOk();
        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/review/approve")
            ->assertCreated()
            ->assertJsonPath('data.result', 'APROBADO');

        // 10. Supervisor despacha.
        $dispatch = $this->actingAs($supervisor, 'sanctum')->postJson("/api/v1/orders/{$orderId}/dispatch", [
            'vehicle_id' => $vehicle->id,
        ])->assertCreated();

        $this->assertEquals(15, $dispatch->json('data.items.0.quantity_dispatched'));

        $this->assertSame(
            'DESPACHADO',
            $this->actingAs($supervisor, 'sanctum')->getJson("/api/v1/orders/{$orderId}")->json('data.status')
        );

        // 11. La línea de tiempo completa responde sin error e incluye el rastro de auditoría.
        $trace = $this->actingAs($supervisor, 'sanctum')->getJson("/api/v1/orders/{$orderId}/trace")->assertOk();
        $this->assertNotEmpty($trace->json('audit_trail'));
    }

    public function test_a_preparer_only_sees_orders_assigned_to_them(): void
    {
        $company = $this->makeCompany();
        $supervisor = $this->makeUser($company, Role::SUPERVISOR);
        $preparerA = $this->makeUser($company, Role::PREPARADOR, ['email' => 'a@'.$company->slug.'.test']);
        $preparerB = $this->makeUser($company, Role::PREPARADOR, ['email' => 'b@'.$company->slug.'.test']);

        $customer = Customer::query()->create([
            'company_id' => $company->id, 'code' => 'CLI-1', 'name' => 'Cliente Uno', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id, 'sku' => 'SKU-1', 'name' => 'Producto Uno', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);

        $order = app(\App\Services\OrderService::class)->create([
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity_requested' => 5, 'unit' => 'UNIDAD']],
        ], $supervisor);

        app(\App\Services\OrderService::class)->assign($order, $preparerA);

        $this->actingAs($preparerA, 'sanctum')->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($preparerB, 'sanctum')->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
