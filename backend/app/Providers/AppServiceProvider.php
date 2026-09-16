<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RouteModel;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\DeliveryPolicy;
use App\Policies\ManageCatalogPolicy;
use App\Policies\RoutePolicy;
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

        // RouteModel no sigue la convención de nombre (evita chocar con
        // Illuminate\Routing\Route), y DeliveryPolicy autoriza sobre la
        // parada (RouteStop) en vez del modelo Delivery, ya que la acción
        // de "ejecutar una entrega" empieza antes de que el registro
        // Delivery exista.
        Gate::policy(RouteModel::class, RoutePolicy::class);
        Gate::policy(RouteStop::class, DeliveryPolicy::class);
    }
}
