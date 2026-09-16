<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Vehicle;
use App\Services\DispatchService;
use App\Services\OrderService;
use App\Services\PreparationService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Cubre Fase 2: ruta -> entrega, con idempotencia por client_uuid
 * (regla 21: "una sincronización no debe generar duplicados").
 */
class RouteDeliveryLifecycleTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function dispatchedOrder($company, $customer, $product, $supervisor, $preparer, $reviewer, float $qty)
    {
        $order = app(OrderService::class)->create([
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity_requested' => $qty, 'unit' => 'UNIDAD']],
        ], $supervisor);

        app(OrderService::class)->assign($order, $preparer);

        $preparation = app(PreparationService::class)->startOrResume($order->fresh(), $preparer);
        app(PreparationService::class)->registerItem($preparation, $preparation->items->first()->order_item_id, $qty, null, null);
        $preparation = app(PreparationService::class)->finish($preparation);

        $review = app(ReviewService::class)->approve($preparation, $reviewer);

        return app(DispatchService::class)->create($review, $supervisor, null, null);
    }

    public function test_route_and_delivery_full_lifecycle_with_idempotent_retry(): void
    {
        $company = $this->makeCompany();
        $supervisor = $this->makeUser($company, Role::SUPERVISOR);
        $preparer = $this->makeUser($company, Role::PREPARADOR);
        $reviewer = $this->makeUser($company, Role::REVISOR);
        $driver = $this->makeUser($company, Role::MOTORISTA);
        $otherDriver = $this->makeUser($company, Role::MOTORISTA, ['email' => 'otro@'.$company->slug.'.test']);

        $customer = Customer::query()->create([
            'company_id' => $company->id, 'code' => 'CLI-1', 'name' => 'Cliente Uno', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id, 'sku' => 'SKU-1', 'name' => 'Producto Uno', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->create([
            'company_id' => $company->id, 'plate' => 'ABC-123', 'is_active' => true,
        ]);

        $dispatch = $this->dispatchedOrder($company, $customer, $product, $supervisor, $preparer, $reviewer, 20);

        // 1. Supervisor crea la ruta.
        $route = $this->actingAs($supervisor, 'sanctum')->postJson('/api/v1/routes', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'dispatch_ids' => [$dispatch->id],
        ])->assertCreated();

        $routeId = $route->json('data.id');
        $stopId = $route->json('data.stops.0.id');

        // 2. El motorista dueño de la ruta la ve en /routes/mine; otro no.
        $this->actingAs($driver, 'sanctum')->getJson('/api/v1/routes/mine')->assertOk();
        $this->actingAs($otherDriver, 'sanctum')->getJson('/api/v1/routes/mine')->assertStatus(404);

        // 3. No se puede entregar antes de iniciar la ruta.
        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/route-stops/{$stopId}/start")
            ->assertStatus(409);

        // 4. Supervisor inicia la ruta -> el pedido pasa a EN_RUTA.
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/v1/routes/{$routeId}/start")->assertOk();
        $this->assertSame(OrderStatus::EN_RUTA, $dispatch->order->fresh()->status);

        // 5. El motorista inicia la parada.
        $this->actingAs($driver, 'sanctum')->postJson("/api/v1/route-stops/{$stopId}/start")->assertOk();

        // 6. Registra la entrega PARCIAL (rechaza 5 unidades).
        $clientUuid = (string) Str::uuid();
        $payload = [
            'client_uuid' => $clientUuid,
            'notes' => 'Cliente rechazó 5 unidades dañadas.',
            'latitude' => 14.1,
            'longitude' => -87.2,
            'items' => [[
                'dispatch_item_id' => $dispatch->items->first()->id,
                'quantity_delivered' => 15,
                'quantity_rejected' => 5,
                'rejection_reason' => 'PRODUCTO_DANADO',
            ]],
        ];

        $first = $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/route-stops/{$stopId}/deliveries", $payload)
            ->assertCreated();

        $this->assertSame('PARCIAL', $first->json('data.result'));
        $this->assertSame(OrderStatus::PARCIAL, $dispatch->order->fresh()->status);

        // 7. Reintentar con el MISMO client_uuid (simula reintento de sync offline):
        // no debe crear una segunda entrega ni fallar. Devuelve 200 (no 201)
        // porque no se crea un registro nuevo, se retorna el existente.
        $second = $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/route-stops/{$stopId}/deliveries", $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, \App\Models\Delivery::query()->where('client_uuid', $clientUuid)->count());

        // 8. La ruta ya no tiene paradas pendientes -> se puede finalizar.
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/v1/routes/{$routeId}/finish")->assertOk();

        // 9. El supervisor puede subir/ver evidencia para resolver disputas,
        // aunque no sea quien la registra en campo.
        $deliveryId = $first->json('data.id');
        $file = \Illuminate\Http\UploadedFile::fake()->image('firma.png');

        $evidence = $this->actingAs($driver, 'sanctum')
            ->post("/api/v1/deliveries/{$deliveryId}/evidence", ['type' => 'FIRMA', 'file' => $file])
            ->assertCreated();

        $evidenceId = $evidence->json('data.id');

        $this->actingAs($supervisor, 'sanctum')->getJson("/api/v1/evidence/{$evidenceId}")->assertOk();
        $this->actingAs($otherDriver, 'sanctum')->getJson("/api/v1/evidence/{$evidenceId}")->assertStatus(403);
    }

    public function test_a_driver_cannot_start_a_stop_from_another_drivers_route(): void
    {
        $company = $this->makeCompany();
        $supervisor = $this->makeUser($company, Role::SUPERVISOR);
        $preparer = $this->makeUser($company, Role::PREPARADOR);
        $reviewer = $this->makeUser($company, Role::REVISOR);
        $driver = $this->makeUser($company, Role::MOTORISTA);
        $intruder = $this->makeUser($company, Role::MOTORISTA, ['email' => 'intruso@'.$company->slug.'.test']);

        $customer = Customer::query()->create([
            'company_id' => $company->id, 'code' => 'CLI-1', 'name' => 'Cliente Uno', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id, 'sku' => 'SKU-1', 'name' => 'Producto Uno', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);
        $vehicle = Vehicle::query()->create([
            'company_id' => $company->id, 'plate' => 'ABC-123', 'is_active' => true,
        ]);

        $dispatch = $this->dispatchedOrder($company, $customer, $product, $supervisor, $preparer, $reviewer, 10);

        $route = app(\App\Services\RouteService::class)->create($vehicle->id, $driver->id, [$dispatch->id], $supervisor);
        app(\App\Services\RouteService::class)->start($route);

        $stopId = $route->stops->first()->id;

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/route-stops/{$stopId}/start")
            ->assertStatus(403);
    }

    /**
     * RouteStop no tiene company_id propio, así que un ADMINISTRADOR de
     * otra empresa no debe poder operar sobre él solo adivinando el id
     * (regla 21: nunca fuga de información entre empresas).
     */
    public function test_an_admin_from_another_company_cannot_operate_on_a_foreign_route_stop(): void
    {
        $companyA = $this->makeCompany('empresa-a');
        $companyB = $this->makeCompany('empresa-b');

        $supervisorB = $this->makeUser($companyB, Role::SUPERVISOR);
        $preparerB = $this->makeUser($companyB, Role::PREPARADOR);
        $reviewerB = $this->makeUser($companyB, Role::REVISOR);
        $driverB = $this->makeUser($companyB, Role::MOTORISTA);
        $adminA = $this->makeUser($companyA, Role::ADMINISTRADOR);

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id, 'code' => 'CLI-1', 'name' => 'Cliente B', 'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'company_id' => $companyB->id, 'sku' => 'SKU-B', 'name' => 'Producto B', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);
        $vehicleB = Vehicle::query()->create([
            'company_id' => $companyB->id, 'plate' => 'XYZ-999', 'is_active' => true,
        ]);

        $dispatch = $this->dispatchedOrder($companyB, $customerB, $productB, $supervisorB, $preparerB, $reviewerB, 10);

        $route = app(\App\Services\RouteService::class)->create($vehicleB->id, $driverB->id, [$dispatch->id], $supervisorB);
        app(\App\Services\RouteService::class)->start($route);

        $stopId = $route->stops->first()->id;

        $this->actingAs($adminA, 'sanctum')
            ->postJson("/api/v1/route-stops/{$stopId}/start")
            ->assertStatus(403);
    }
}
