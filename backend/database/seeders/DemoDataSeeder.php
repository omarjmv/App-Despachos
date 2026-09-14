<?php

namespace Database\Seeders;

use App\Enums\DifferenceReason;
use App\Enums\OrderStatus;
use App\Enums\PreparationStatus;
use App\Enums\ReviewResult;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Order;
use App\Models\Preparation;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\SequenceGenerator;
use Illuminate\Database\Seeder;

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

        // Pedido DESPACHADO: recorrido completo hasta despacho (entrega llega en Fase 2).
        $order5 = $this->makeOrder($company, $customers['Colonial 1'], $users[Role::ADMINISTRADOR], OrderStatus::DESPACHADO, [
            [$products['AGU-1L'], 90], [$products['AGU-5L'], 20],
        ], assignedTo: $users[Role::PREPARADOR]);
        $prep5 = $this->preparar($order5, $users[Role::PREPARADOR], [90, 20], [null, null]);
        $review5 = Review::query()->create([
            'company_id' => $company->id,
            'preparation_id' => $prep5->id,
            'reviewed_by' => $users[Role::REVISOR]->id,
            'result' => ReviewResult::APROBADO,
            'reviewed_at' => now(),
        ]);
        $dispatch = Dispatch::query()->create([
            'company_id' => $company->id,
            'number' => SequenceGenerator::next('dispatches', 'number', $company->id, 'DES'),
            'order_id' => $order5->id,
            'review_id' => $review5->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $users[Role::MOTORISTA]->id,
            'dispatched_by' => $users[Role::SUPERVISOR]->id,
            'dispatched_at' => now(),
        ]);
        foreach ($prep5->items as $item) {
            $dispatch->items()->create([
                'preparation_item_id' => $item->id,
                'quantity_dispatched' => $item->quantity_prepared,
            ]);
        }
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
