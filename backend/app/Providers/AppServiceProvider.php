<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\ManageCatalogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catálogos que comparten la misma regla de autorización
        // (lectura amplia, escritura solo ADMINISTRADOR).
        foreach ([Customer::class, Product::class, Vehicle::class, User::class] as $model) {
            Gate::policy($model, ManageCatalogPolicy::class);
        }
    }
}
