<?php

namespace App\Services;

use App\Enums\DeliveryResult;
use App\Enums\EvidenceType;
use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryEvidence;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function startStop(RouteStop $stop): RouteStop
    {
        if ($stop->route->status !== RouteStatus::EN_CURSO) {
            throw new InvalidStateTransitionException('La ruta debe estar EN_CURSO para iniciar una entrega.');
        }
        if ($stop->status !== RouteStopStatus::PENDIENTE) {
            throw new InvalidStateTransitionException('Esta parada ya fue iniciada o completada.');
        }

        $stop->update(['status' => RouteStopStatus::EN_CURSO]);

        return $stop;
    }

    /**
     * Crea la entrega o, si ya se procesó ese client_uuid (reintento de
     * sincronización offline), retorna la existente sin duplicar
     * (regla 21: "una sincronización no debe generar duplicados").
     *
     * @param  array<int, array{dispatch_item_id:int, quantity_delivered:float, quantity_rejected?:float, rejection_reason?:string}>  $items
     */
    public function createOrGet(
        RouteStop $stop,
        User $driver,
        string $clientUuid,
        array $items,
        ?string $notes,
        ?float $latitude,
        ?float $longitude,
        ?string $deliveredAt = null,
    ): Delivery {
        $existing = Delivery::query()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return $existing->load('items', 'evidence');
        }

        if ($stop->route->status !== RouteStatus::EN_CURSO) {
            throw new InvalidStateTransitionException('La ruta debe estar EN_CURSO para registrar una entrega.');
        }
        if ($stop->status === RouteStopStatus::COMPLETADA) {
            throw new InvalidStateTransitionException('Esta parada ya fue entregada.');
        }
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Debe registrar al menos un producto entregado.']);
        }

        $dispatchItems = $stop->dispatch->items()->get()->keyBy('id');
        $totalDispatched = 0.0;
        $totalDelivered = 0.0;

        foreach ($items as $item) {
            $dispatchItem = $dispatchItems->get($item['dispatch_item_id']);

            if (! $dispatchItem) {
                throw ValidationException::withMessages(['items' => 'Un producto no pertenece a este despacho.']);
            }

            $delivered = (float) $item['quantity_delivered'];
            $rejected = (float) ($item['quantity_rejected'] ?? 0);

            if ($delivered < 0 || $rejected < 0) {
                throw ValidationException::withMessages(['items' => 'Las cantidades no pueden ser negativas.']);
            }
            if ($delivered > (float) $dispatchItem->quantity_dispatched) {
                throw ValidationException::withMessages(['items' => 'La cantidad entregada no puede superar lo despachado.']);
            }

            $totalDispatched += (float) $dispatchItem->quantity_dispatched;
            $totalDelivered += $delivered;
        }

        $result = match (true) {
            $totalDelivered <= 0 => DeliveryResult::RECHAZADA,
            $totalDelivered >= $totalDispatched => DeliveryResult::COMPLETA,
            default => DeliveryResult::PARCIAL,
        };

        return DB::transaction(function () use ($stop, $driver, $clientUuid, $items, $notes, $latitude, $longitude, $deliveredAt, $result) {
            $delivery = Delivery::query()->create([
                'company_id' => $stop->dispatch->company_id,
                'route_stop_id' => $stop->id,
                'dispatch_id' => $stop->dispatch_id,
                'client_uuid' => $clientUuid,
                'delivered_by' => $driver->id,
                'result' => $result,
                'rejection_reason' => $result === DeliveryResult::RECHAZADA ? ($notes ?: 'No especificado') : null,
                'notes' => $notes,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'delivered_at' => $deliveredAt ?? now(),
                'synced_at' => now(),
            ]);

            foreach ($items as $item) {
                $delivery->items()->create([
                    'dispatch_item_id' => $item['dispatch_item_id'],
                    'quantity_delivered' => $item['quantity_delivered'],
                    'quantity_rejected' => $item['quantity_rejected'] ?? 0,
                    'rejection_reason' => $item['rejection_reason'] ?? null,
                ]);
            }

            $stop->update(['status' => RouteStopStatus::COMPLETADA]);

            $stop->dispatch->order->update([
                'status' => $result === DeliveryResult::COMPLETA ? OrderStatus::ENTREGADO : OrderStatus::PARCIAL,
            ]);

            AuditLog::record('REGISTRAR_ENTREGA', $delivery, ['result' => $result->value]);

            return $delivery->load('items', 'evidence');
        });
    }

    /**
     * Las fotografías/firmas se comprimen en el cliente antes de subir
     * (Documento 4 §4); aquí solo se persisten en un disco privado, nunca
     * público, y se sirven luego mediante rutas autorizadas.
     */
    public function addEvidence(Delivery $delivery, EvidenceType $type, UploadedFile $file): DeliveryEvidence
    {
        $path = $file->store("companies/{$delivery->company_id}/deliveries/{$delivery->id}", 'local');

        return $delivery->evidence()->create([
            'type' => $type,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'captured_at' => now(),
        ]);
    }
}
