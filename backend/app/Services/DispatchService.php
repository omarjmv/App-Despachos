<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReviewResult;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\AuditLog;
use App\Models\Dispatch;
use App\Models\Review;
use App\Models\User;
use App\Support\SequenceGenerator;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public function create(Review $review, User $dispatchedBy, ?int $vehicleId, ?int $driverId): Dispatch
    {
        if ($review->result !== ReviewResult::APROBADO) {
            throw new InvalidStateTransitionException('Solo una revisión APROBADA puede convertirse en despacho.');
        }

        $order = $review->preparation->order;

        if ($order->status !== OrderStatus::APROBADO) {
            throw new InvalidStateTransitionException(
                "El pedido debe estar APROBADO para despacharse (actual: {$order->status->value})."
            );
        }

        return DB::transaction(function () use ($review, $order, $dispatchedBy, $vehicleId, $driverId) {
            $dispatch = Dispatch::query()->create([
                'company_id' => $order->company_id,
                'number' => SequenceGenerator::next('dispatches', 'number', $order->company_id, 'DES'),
                'order_id' => $order->id,
                'review_id' => $review->id,
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'dispatched_by' => $dispatchedBy->id,
                'dispatched_at' => now(),
            ]);

            foreach ($review->preparation->items as $preparationItem) {
                $dispatch->items()->create([
                    'preparation_item_id' => $preparationItem->id,
                    'quantity_dispatched' => $preparationItem->quantity_prepared,
                ]);
            }

            $order->update(['status' => OrderStatus::DESPACHADO]);

            AuditLog::record('CREAR_DESPACHO', $order, ['dispatch_id' => $dispatch->id]);

            return $dispatch->load('items', 'vehicle', 'driver');
        });
    }
}
