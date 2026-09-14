<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

trait CreatesTenants
{
    protected function makeCompany(string $slug = 'empresa-uno'): Company
    {
        return Company::query()->create([
            'name' => 'Empresa '.strtoupper($slug),
            'slug' => $slug,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'date_format' => 'd/m/Y',
            'is_active' => true,
        ]);
    }

    protected function makeUser(Company $company, string $roleName, array $overrides = []): User
    {
        $this->seedRolesOnce();

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::query()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $overrides['name'] ?? $roleName.' Demo',
            'email' => $overrides['email'] ?? strtolower($roleName).'@'.$company->slug.'.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function seedRolesOnce(): void
    {
        if (Role::query()->count() === 0) {
            $this->seed(RolePermissionSeeder::class);
        }
    }
}
