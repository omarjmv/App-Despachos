<?php

namespace App\Services;

use App\Models\RouteStop;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa en lote las entregas que el motorista registró sin conexión
 * (Documento 4 §4: LOCAL -> SYNC -> SERVER). Cada operación es
 * independiente: que una falle no bloquea a las demás, y reintentar el
 * lote completo nunca duplica nada porque DeliveryService::createOrGet
 * ya es idempotente por client_uuid.
 */
class SyncService
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    /**
     * @param  array<int, array>  $operations
     * @return array<int, array{client_uuid:string, status:string, delivery_id?:int, message?:string}>
     */
    public function processDeliveryBatch(array $operations, User $driver, ?string $deviceId): array
    {
        return array_map(fn (array $operation) => $this->processOne($operation, $driver, $deviceId), $operations);
    }

    private function processOne(array $operation, User $driver, ?string $deviceId): array
    {
        $clientUuid = $operation['client_uuid'];

        $existingLog = SyncOperation::query()
            ->where('entity_type', 'delivery')
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existingLog?->status === 'PROCESADA') {
            return ['client_uuid' => $clientUuid, 'status' => 'ya_sincronizada'];
        }

        try {
            $stop = RouteStop::query()->findOrFail($operation['route_stop_id']);

            if (! Gate::forUser($driver)->allows('execute', $stop)) {
                throw new \RuntimeException('No autorizado para entregar esta parada.');
            }

            $delivery = $this->deliveries->createOrGet(
                $stop,
                $driver,
                $clientUuid,
                $operation['items'],
                $operation['notes'] ?? null,
                $operation['latitude'] ?? null,
                $operation['longitude'] ?? null,
                $operation['delivered_at'] ?? null,
            );

            SyncOperation::query()->updateOrCreate(
                ['entity_type' => 'delivery', 'client_uuid' => $clientUuid],
                [
                    'company_id' => $driver->company_id,
                    'device_id' => $deviceId,
                    'payload' => $operation,
                    'status' => 'PROCESADA',
                    'processed_at' => now(),
                ]
            );

            return ['client_uuid' => $clientUuid, 'status' => 'creada', 'delivery_id' => $delivery->id];
        } catch (Throwable $e) {
            Log::warning('Fallo al sincronizar entrega offline', ['client_uuid' => $clientUuid, 'error' => $e->getMessage()]);

            SyncOperation::query()->updateOrCreate(
                ['entity_type' => 'delivery', 'client_uuid' => $clientUuid],
                [
                    'company_id' => $driver->company_id,
                    'device_id' => $deviceId,
                    'payload' => $operation,
                    'status' => 'ERROR',
                    'error_message' => $e->getMessage(),
                ]
            );

            return ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
