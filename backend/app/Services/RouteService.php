<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\AuditLog;
use App\Models\Dispatch;
use App\Models\RouteModel;
use App\Models\User;
use App\Support\SequenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteService
{
    /**
     * @param  int[]  $dispatchIds  orden deseado de las paradas (índice = secuencia)
     */
    public function create(int $vehicleId, int $driverId, array $dispatchIds, User $creator): RouteModel
    {
        if (empty($dispatchIds)) {
            throw ValidationException::withMessages(['dispatch_ids' => 'La ruta debe incluir al menos un despacho.']);
        }

        $dispatches = Dispatch::query()->with('order')->whereIn('id', $dispatchIds)->get()->keyBy('id');

        foreach ($dispatchIds as $dispatchId) {
            $dispatch = $dispatches->get($dispatchId);

            if (! $dispatch) {
                throw ValidationException::withMessages(['dispatch_ids' => "El despacho {$dispatchId} no existe."]);
            }
            if ($dispatch->routeStop()->exists()) {
                throw ValidationException::withMessages(['dispatch_ids' => "El despacho {$dispatch->number} ya pertenece a una ruta."]);
            }
            if ($dispatch->order->status !== OrderStatus::DESPACHADO) {
                throw ValidationException::withMessages(['dispatch_ids' => "El despacho {$dispatch->number} no está en estado DESPACHADO."]);
            }
        }

        return DB::transaction(function () use ($vehicleId, $driverId, $dispatchIds, $creator) {
            $route = RouteModel::query()->create([
                'company_id' => $creator->company_id,
                'code' => SequenceGenerator::next('routes', 'code', $creator->company_id, 'RUTA'),
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'status' => RouteStatus::PLANIFICADA,
            ]);

            foreach (array_values($dispatchIds) as $index => $dispatchId) {
                $route->stops()->create([
                    'dispatch_id' => $dispatchId,
                    'sequence' => $index + 1,
                    'status' => RouteStopStatus::PENDIENTE,
                ]);
            }

            return $route->load('stops.dispatch.order.customer', 'vehicle', 'driver');
        });
    }

    /**
     * @param  int[]  $stopIds  ids de route_stops en el nuevo orden deseado
     */
    public function reorder(RouteModel $route, array $stopIds): RouteModel
    {
        $this->assertPlanned($route);

        $stops = $route->stops()->pluck('id');

        if ($stops->sort()->values()->all() !== collect($stopIds)->sort()->values()->all()) {
            throw ValidationException::withMessages(['stop_ids' => 'La lista debe incluir exactamente las paradas de esta ruta.']);
        }

        DB::transaction(function () use ($route, $stopIds) {
            foreach (array_values($stopIds) as $index => $stopId) {
                $route->stops()->where('id', $stopId)->update(['sequence' => $index + 1]);
            }
        });

        return $route->fresh('stops.dispatch.order.customer');
    }

    public function start(RouteModel $route): RouteModel
    {
        $this->assertTransition($route, RouteStatus::EN_CURSO);

        DB::transaction(function () use ($route) {
            $route->update(['status' => RouteStatus::EN_CURSO, 'started_at' => now()]);

            foreach ($route->stops as $stop) {
                $stop->dispatch->order->update(['status' => OrderStatus::EN_RUTA]);
            }
        });

        AuditLog::record('INICIAR_RUTA', $route);

        return $route->fresh('stops.dispatch.order');
    }

    public function finish(RouteModel $route): RouteModel
    {
        $this->assertTransition($route, RouteStatus::FINALIZADA);

        $pending = $route->stops()->whereIn('status', [RouteStopStatus::PENDIENTE, RouteStopStatus::EN_CURSO])->exists();
        if ($pending) {
            throw new InvalidStateTransitionException('No se puede finalizar la ruta: hay paradas sin completar.');
        }

        $route->update(['status' => RouteStatus::FINALIZADA, 'finished_at' => now()]);

        AuditLog::record('FINALIZAR_RUTA', $route);

        return $route;
    }

    private function assertPlanned(RouteModel $route): void
    {
        if ($route->status !== RouteStatus::PLANIFICADA) {
            throw new InvalidStateTransitionException('Solo se puede reordenar una ruta que aún no ha iniciado.');
        }
    }

    private function assertTransition(RouteModel $route, RouteStatus $target): void
    {
        if (! $route->status->canTransitionTo($target)) {
            throw new InvalidStateTransitionException(
                "No se puede pasar la ruta de {$route->status->value} a {$target->value}."
            );
        }
    }
}
