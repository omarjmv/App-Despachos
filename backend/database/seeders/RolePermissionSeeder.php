<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles y permisos globales (no dependen de una empresa), según la
     * sección 4 del brief. Se seedean una única vez.
     */
    public function run(): void
    {
        $matrix = [
            Role::ADMINISTRADOR => [
                'companies.manage', 'users.manage', 'roles.manage', 'customers.manage',
                'products.manage', 'vehicles.manage', 'settings.manage', 'reports.view_all',
            ],
            Role::SUPERVISOR => [
                'orders.view', 'orders.assign', 'preparation.supervise',
                'dispatch.review', 'routes.create', 'reports.view',
            ],
            Role::PREPARADOR => [
                'orders.view_assigned', 'preparation.execute', 'quantities.register',
                'scanner.use', 'preparation.finish',
            ],
            Role::REVISOR => [
                'quantities.review', 'preparation.validate', 'differences.detect',
                'dispatch.approve_reject',
            ],
            Role::MOTORISTA => [
                'route.view_assigned', 'deliveries.view', 'delivery.start',
                'delivery.register_quantities', 'signature.capture', 'photo.capture',
                'observations.register', 'delivery.finish',
            ],
        ];

        foreach ($matrix as $roleName => $permissionSlugs) {
            $role = Role::query()->firstOrCreate(['name' => $roleName]);

            $permissionIds = collect($permissionSlugs)->map(
                fn (string $slug) => Permission::query()->firstOrCreate(['slug' => $slug])->id
            );

            $role->permissions()->sync($permissionIds);
        }
    }
}
