<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\SyncDeliveriesBatchRequest;
use App\Services\SyncService;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $sync) {}

    /**
     * Envío en lote de entregas registradas offline. Cada operación es
     * idempotente por client_uuid: reenviar el mismo lote (o parte de
     * él) tras un corte de red nunca duplica una entrega.
     */
    public function deliveriesBatch(SyncDeliveriesBatchRequest $request)
    {
        $results = $this->sync->processDeliveryBatch(
            $request->validated('operations'),
            $request->user(),
            $request->validated('device_id'),
        );

        return response()->json(['results' => $results]);
    }
}
