<?php

namespace Database\Seeders;

use App\Enums\DifferenceReason;
use App\Enums\OrderStatus;
use App\Enums\PreparationStatus;
use App\Enums\ReviewResult;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Order;
use App\Models\Preparation;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\RouteModel;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DeliveryService;
use App\Support\SequenceGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos ficticios para poder demostrar el sistema (sección 26 del brief).
 * Ningún nombre de empresa real: todo es configurable vía Company.name.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['slug' => 'demo-distribuciones'],
            [
                'name' => 'Demo Distribuciones',
                'currency' => 'HNL',
                'timezone' => 'America/Tegucigalpa',
                'date_format' => 'd/m/Y',
                'settings' => ['allow_overpack' => false],
                'is_active' => true,
            ]
        );

        $roles = Role::query()->pluck('id', 'name');

        $users = [];
        foreach ([
            Role::ADMINISTRADOR => ['name' => 'Ana Administradora', 'email' => 'admin@demo.com'],
            Role::SUPERVISOR => ['name' => 'Carlos Supervisor', 'email' => 'supervisor@demo.com'],
            Role::PREPARADOR => ['name' => 'María Preparadora', 'email' => 'preparador@demo.com'],
            Role::REVISOR => ['name' => 'Luis Revisor', 'email' => 'revisor@demo.com'],
            Role::MOTORISTA => ['name' => 'Pedro Motorista', 'email' => 'motorista@demo.com'],
        ] as $roleName => $info) {
            $users[$roleName] = User::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => $info['email']],
                [
                    'role_id' => $roles[$roleName],
                    'name' => $info['name'],
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
        }

        $customers = collect(['Colonial 1', 'Comercial ABC', 'Distribuidora XYZ', 'Supermercado Central'])
            ->mapWithKeys(fn (string $name, int $i) => [$name => Customer::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'CLI-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                ['name' => $name, 'phone' => '+504 9999-000'.$i, 'is_active' => true]
            )]);

        $products = collect([
            ['sku' => 'AGU-500', 'barcode' => '7501234500018', 'name' => 'Agua 500ml', 'unit' => 'UNIDAD'],
            ['sku' => 'AGU-1L', 'barcode' => '7501234510017', 'name' => 'Agua 1L', 'unit' => 'UNIDAD'],
            ['sku' => 'AGU-5L', 'barcode' => '7501234550013', 'name' => 'Agua 5L', 'unit' => 'UNIDAD'],
            ['sku' => 'DEMO-01', 'barcode' => '7501234990012', 'name' => 'Producto Demo 01', 'unit' => 'CAJA'],
        ])->mapWithKeys(fn (array $p) => [$p['sku'] => Product::query()->updateOrCreate(
            ['company_id' => $company->id, 'sku' => $p['sku']],
            [...$p, 'is_active' => true]
        )]);

        $vehicle = Vehicle::query()->updateOrCreate(
            ['company_id' => $company->id, 'plate' => 'HN-1234'],
            ['brand' => 'Toyota', 'model' => 'Hilux', 'capacity' => 2000, 'is_active' => true]
        );

        // Pedido PENDIENTE: recién creado, sin preparar todavía.
        $this->makeOrder($company, $customers['Supermercado Central'], $users[Role::ADMINISTRADOR], OrderStatus::PENDIENTE, [
            [$products['AGU-500'], 100], [$products['AGU-1L'], 80],
        ]);

        // Pedido PREPARANDO: asignado a la preparadora, aún sin registrar cantidades.
        $this->makeOrder($company, $customers['Comercial ABC'], $users[Role::ADMINISTRADOR], OrderStatus::PREPARANDO, [
            [$products['DEMO-01'], 30],
        ], assignedTo: $users[Role::PREPARADOR]);

        // Pedido PREPARADO (parcial): preparación finalizada con diferencia justificada.
        $order3 = $this->makeOrder($company, $customers['Colonial 1'], $users[Role::ADMINISTRADOR], OrderStatus::PREPARADO, [
            [$products['AGU-500'], 50], [$products['AGU-1L'], 60], [$products['AGU-5L'], 40],
        ], assignedTo: $users[Role::PREPARADOR]);
        $this->preparar($order3, $users[Role::PREPARADOR], [50, 55, 40], [null, DifferenceReason::FALTANTE_INVENTARIO, null]);

        // Pedido APROBADO: preparación revisada y aprobada, lista para despachar.
        $order4 = $this->makeOrder($company, $customers['Distribuidora XYZ'], $users[Role::ADMINISTRADOR], OrderStatus::APROBADO, [
            [$products['AGU-500'], 120],
        ], assignedTo: $users[Role::PREPARADOR]);
        $prep4 = $this->preparar($order4, $users[Role::PREPARADOR], [120], [null]);
        Review::query()->create([
            'company_id' => $company->id,
            'preparation_id' => $prep4->id,
            'reviewed_by' => $users[Role::REVISOR]->id,
            'result' => ReviewResult::APROBADO,
            'reviewed_at' => now(),
        ]);

        // Pedido DESPACHADO: recorrido completo hasta despacho, aún sin ruta.
        $order5 = $this->makeOrder($company, $customers['Colonial 1'], $users[Role::ADMINISTRADOR], OrderStatus::DESPACHADO, [
            [$products['AGU-1L'], 90], [$products['AGU-5L'], 20],
        ], assignedTo: $users[Role::PREPARADOR]);
        $prep5 = $this->preparar($order5, $users[Role::PREPARADOR], [90, 20], [null, null]);
        $this->despachar($company, $order5, $prep5, $users, $vehicle);

        // Fase 2: tres pedidos despachados que se agrupan en una ruta con
        // entrega COMPLETA, PARCIAL y RECHAZADA (sección 26 del brief).
        $orderCompleta = $this->makeOrder($company, $customers['Comercial ABC'], $users[Role::ADMINISTRADOR], OrderStatus::DESPACHADO, [
            [$products['AGU-500'], 40],
        ], assignedTo: $users[Role::PREPARADOR]);
        $prepCompleta = $this->preparar($orderCompleta, $users[Role::PREPARADOR], [40], [null]);
        $dispatchCompleta = $this->despachar($company, $orderCompleta, $prepCompleta, $users, $vehicle);

        $orderParcial = $this->makeOrder($company, $customers['Distribuidora XYZ'], $users[Role::ADMINISTRADOR], OrderStatus::DESPACHADO, [
            [$products['AGU-1L'], 30],
        ], assignedTo: $users[Role::PREPARADOR]);
        $prepParcial = $this->preparar($orderParcial, $users[Role::PREPARADOR], [30], [null]);
        $dispatchParcial = $this->despachar($company, $orderParcial, $prepParcial, $users, $vehicle);

        $orderRechazada = $this->makeOrder($company, $customers['Supermercado Central'], $users[Role::ADMINISTRADOR], OrderStatus::DESPACHADO, [
            [$products['AGU-5L'], 15],
        ], assignedTo: $users[Role::PREPARADOR]);
        $prepRechazada = $this->preparar($orderRechazada, $users[Role::PREPARADOR], [15], [null]);
        $dispatchRechazada = $this->despachar($company, $orderRechazada, $prepRechazada, $users, $vehicle);

        $route = RouteModel::query()->create([
            'company_id' => $company->id,
            'code' => SequenceGenerator::next('routes', 'code', $company->id, 'RUTA'),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $users[Role::MOTORISTA]->id,
            'status' => RouteStatus::EN_CURSO,
            'started_at' => now()->subHour(),
        ]);

        $stops = collect([$dispatchCompleta, $dispatchParcial, $dispatchRechazada])
            ->values()
            ->map(fn (Dispatch $dispatch, int $i) => $route->stops()->create([
                'dispatch_id' => $dispatch->id,
                'sequence' => $i + 1,
                'status' => RouteStopStatus::PENDIENTE,
            ]));

        $deliveryService = app(DeliveryService::class);

        // Entrega COMPLETA: se entrega exactamente lo despachado.
        $stopCompleta = $stops[0];
        $deliveryService->createOrGet(
            $stopCompleta->fresh(),
            $users[Role::MOTORISTA],
            (string) Str::uuid(),
            $dispatchCompleta->items->map(fn ($item) => [
                'dispatch_item_id' => $item->id,
                'quantity_delivered' => (float) $item->quantity_dispatched,
            ])->all(),
            null,
            14.0723,
            -87.1921,
        );

        // Entrega PARCIAL: el cliente rechaza parte por producto dañado.
        $stopParcial = $stops[1];
        $deliveryService->createOrGet(
            $stopParcial->fresh(),
            $users[Role::MOTORISTA],
            (string) Str::uuid(),
            $dispatchParcial->items->map(fn ($item) => [
                'dispatch_item_id' => $item->id,
                'quantity_delivered' => max(0, (float) $item->quantity_dispatched - 5),
                'quantity_rejected' => 5,
                'rejection_reason' => 'Producto dañado en tránsito',
            ])->all(),
            'Cliente rechazó 5 unidades por daño en el empaque.',
            14.0801,
            -87.2050,
        );

        // Entrega RECHAZADA: el cliente no recibe nada.
        $stopRechazada = $stops[2];
        $deliveryService->createOrGet(
            $stopRechazada->fresh(),
            $users[Role::MOTORISTA],
            (string) Str::uuid(),
            $dispatchRechazada->items->map(fn ($item) => [
                'dispatch_item_id' => $item->id,
                'quantity_delivered' => 0,
                'quantity_rejected' => (float) $item->quantity_dispatched,
                'rejection_reason' => 'Cliente cerrado, no recibió el pedido',
            ])->all(),
            'Local cerrado al momento de la entrega.',
            14.0654,
            -87.1815,
        );
    }

    private function despachar(Company $company, Order $order, Preparation $preparation, array $users, Vehicle $vehicle): Dispatch
    {
        $review = Review::query()->create([
            'company_id' => $company->id,
            'preparation_id' => $preparation->id,
            'reviewed_by' => $users[Role::REVISOR]->id,
            'result' => ReviewResult::APROBADO,
            'reviewed_at' => now(),
        ]);

        $dispatch = Dispatch::query()->create([
            'company_id' => $company->id,
            'number' => SequenceGenerator::next('dispatches', 'number', $company->id, 'DES'),
            'order_id' => $order->id,
            'review_id' => $review->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $users[Role::MOTORISTA]->id,
            'dispatched_by' => $users[Role::SUPERVISOR]->id,
            'dispatched_at' => now(),
        ]);

        foreach ($preparation->items as $item) {
            $dispatch->items()->create([
                'preparation_item_id' => $item->id,
                'quantity_dispatched' => $item->quantity_prepared,
            ]);
        }

        return $dispatch->load('items');
    }

    private function makeOrder(Company $company, Customer $customer, User $creator, OrderStatus $status, array $items, ?User $assignedTo = null): Order
    {
        $order = Order::query()->create([
            'company_id' => $company->id,
            'number' => SequenceGenerator::next('orders', 'number', $company->id, 'PED'),
            'customer_id' => $customer->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignedTo?->id,
            'order_date' => now()->toDateString(),
            'status' => $status,
        ]);

        foreach ($items as [$product, $qty]) {
            $order->items()->create([
                'product_id' => $product->id,
                'quantity_requested' => $qty,
                'unit' => $product->unit,
            ]);
        }

        return $order;
    }

    private function preparar(Order $order, User $preparer, array $preparedQuantities, array $reasons): Preparation
    {
        $preparation = $order->preparations()->create([
            'company_id' => $order->company_id,
            'prepared_by' => $preparer->id,
            'status' => PreparationStatus::FINALIZADA,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
        ]);

        foreach ($order->items as $index => $item) {
            $preparation->items()->create([
                'order_item_id' => $item->id,
                'quantity_prepared' => $preparedQuantities[$index] ?? $item->quantity_requested,
                'difference_reason' => $reasons[$index] ?? null,
            ]);
        }

        return $preparation->load('items.orderItem.product');
    }
}
