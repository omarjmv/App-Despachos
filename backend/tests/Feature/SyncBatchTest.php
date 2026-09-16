<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Vehicle;
use App\Services\DispatchService;
use App\Services\OrderService;
use App\Services\PreparationService;
use App\Services\ReviewService;
use App\Services\RouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Fase 3: sincronización en lote de entregas registradas offline
 * (Documento 4 §4: LOCAL -> SYNC QUEUE -> SERVER).
 */
class SyncBatchTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_batch_sync_is_idempotent_across_retries_and_reports_per_item_status(): void
    {
        $company = $this->makeCompany();
        $supervisor = $this->makeUser($company, Role::SUPERVISOR);
        $preparer = $this->makeUser($company, Role::PREPARADOR);
        $reviewer = $this->makeUser($company, Role::REVISOR);
        $driver = $this->makeUser($company, Role::MOTORISTA);

        $customer = Customer::query()->create([
            'company_id' => $company->id, 'code' => 'CLI-1', 'name' => 'Cliente Uno', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id, 'sku' => 'SKU-1', 'name' => 'Producto Uno', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->create([
            'company_id' => $company->id, 'plate' => 'ABC-123', 'is_active' => true,
        ]);

        $order = app(OrderService::class)->create([
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity_requested' => 10, 'unit' => 'UNIDAD']],
        ], $supervisor);
        app(OrderService::class)->assign($order, $preparer);
        $preparation = app(PreparationService::class)->startOrResume($order->fresh(), $preparer);
        app(PreparationService::class)->registerItem($preparation, $preparation->items->first()->order_item_id, 10, null, null);
        $preparation = app(PreparationService::class)->finish($preparation);
        $review = app(ReviewService::class)->approve($preparation, $reviewer);
        $dispatch = app(DispatchService::class)->create($review, $supervisor, null, null);

        $route = app(RouteService::class)->create($vehicle->id, $driver->id, [$dispatch->id], $supervisor);
        app(RouteService::class)->start($route);
        $stopId = $route->stops->first()->id;

        $clientUuid = (string) Str::uuid();
        $batch = [
            'device_id' => 'device-abc',
            'operations' => [[
                'route_stop_id' => $stopId,
                'client_uuid' => $clientUuid,
                'items' => [[
                    'dispatch_item_id' => $dispatch->items->first()->id,
                    'quantity_delivered' => 10,
                ]],
            ]],
        ];

        // 1. Primer envío: crea la entrega.
        $first = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/sync/deliveries/batch', $batch)
            ->assertOk();

        $this->assertSame('creada', $first->json('results.0.status'));
        $this->assertSame(1, \App\Models\Delivery::query()->where('client_uuid', $clientUuid)->count());

        // 2. Reintento del MISMO lote (simula que el dispositivo no vio la
        // respuesta y reintenta tras recuperar señal): no debe duplicar.
        $second = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/sync/deliveries/batch', $batch)
            ->assertOk();

        $this->assertSame('ya_sincronizada', $second->json('results.0.status'));
        $this->assertSame(1, \App\Models\Delivery::query()->where('client_uuid', $clientUuid)->count());
    }

    public function test_batch_sync_reports_error_for_an_unauthorized_stop_without_failing_the_whole_batch(): void
    {
        $companyA = $this->makeCompany('empresa-a');
        $companyB = $this->makeCompany('empresa-b');

        $supervisorB = $this->makeUser($companyB, Role::SUPERVISOR);
        $preparerB = $this->makeUser($companyB, Role::PREPARADOR);
        $reviewerB = $this->makeUser($companyB, Role::REVISOR);
        $driverB = $this->makeUser($companyB, Role::MOTORISTA);
        $driverA = $this->makeUser($companyA, Role::MOTORISTA);

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id, 'code' => 'CLI-1', 'name' => 'Cliente B', 'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'company_id' => $companyB->id, 'sku' => 'SKU-B', 'name' => 'Producto B', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);
        $vehicleB = Vehicle::query()->create([
            'company_id' => $companyB->id, 'plate' => 'XYZ-1', 'is_active' => true,
        ]);

        $order = app(OrderService::class)->create([
            'customer_id' => $customerB->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $productB->id, 'quantity_requested' => 5, 'unit' => 'UNIDAD']],
        ], $supervisorB);
        app(OrderService::class)->assign($order, $preparerB);
        $preparation = app(PreparationService::class)->startOrResume($order->fresh(), $preparerB);
        app(PreparationService::class)->registerItem($preparation, $preparation->items->first()->order_item_id, 5, null, null);
        $preparation = app(PreparationService::class)->finish($preparation);
        $review = app(ReviewService::class)->approve($preparation, $reviewerB);
        $dispatch = app(DispatchService::class)->create($review, $supervisorB, null, null);

        $route = app(RouteService::class)->create($vehicleB->id, $driverB->id, [$dispatch->id], $supervisorB);
        app(RouteService::class)->start($route);
        $stopId = $route->stops->first()->id;

        // driverA (otra empresa) intenta sincronizar una entrega para una parada de B.
        $response = $this->actingAs($driverA, 'sanctum')->postJson('/api/v1/sync/deliveries/batch', [
            'operations' => [[
                'route_stop_id' => $stopId,
                'client_uuid' => (string) Str::uuid(),
                'items' => [[
                    'dispatch_item_id' => $dispatch->items->first()->id,
                    'quantity_delivered' => 5,
                ]],
            ]],
        ])->assertOk();

        $this->assertSame('error', $response->json('results.0.status'));
        $this->assertSame(0, \App\Models\Delivery::query()->count());
    }
}
