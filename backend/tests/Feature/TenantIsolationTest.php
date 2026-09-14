<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Regla 21 del brief: "Nunca permitir que un usuario vea información de otra empresa".
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_user_cannot_see_customers_from_another_company(): void
    {
        $companyA = $this->makeCompany('empresa-a');
        $companyB = $this->makeCompany('empresa-b');

        $adminA = $this->makeUser($companyA, Role::ADMINISTRADOR);
        $this->makeUser($companyB, Role::ADMINISTRADOR);

        Customer::query()->create([
            'company_id' => $companyA->id, 'code' => 'A-1', 'name' => 'Cliente de A', 'is_active' => true,
        ]);
        Customer::query()->create([
            'company_id' => $companyB->id, 'code' => 'B-1', 'name' => 'Cliente de B', 'is_active' => true,
        ]);

        $response = $this->actingAs($adminA, 'sanctum')->getJson('/api/v1/customers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Cliente de A'));
        $this->assertFalse($names->contains('Cliente de B'));
    }

    public function test_a_user_cannot_fetch_an_order_by_id_from_another_company(): void
    {
        $companyA = $this->makeCompany('empresa-a');
        $companyB = $this->makeCompany('empresa-b');

        $adminA = $this->makeUser($companyA, Role::ADMINISTRADOR);
        $adminB = $this->makeUser($companyB, Role::ADMINISTRADOR);

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id, 'code' => 'B-1', 'name' => 'Cliente de B', 'is_active' => true,
        ]);
        $product = \App\Models\Product::query()->create([
            'company_id' => $companyB->id, 'sku' => 'SKU-B', 'name' => 'Producto B', 'unit' => 'UNIDAD', 'is_active' => true,
        ]);

        $orderB = app(\App\Services\OrderService::class)->create([
            'customer_id' => $customerB->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity_requested' => 5, 'unit' => 'UNIDAD']],
        ], $adminB);

        // El global scope hace que, desde el contexto de la empresa A, el pedido de B "no exista".
        $response = $this->actingAs($adminA, 'sanctum')->getJson("/api/v1/orders/{$orderB->id}");

        $response->assertStatus(404);
    }
}
