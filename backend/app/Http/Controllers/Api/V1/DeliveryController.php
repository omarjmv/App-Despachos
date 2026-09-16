<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\CreateDeliveryRequest;
use App\Http\Requests\Delivery\UploadEvidenceRequest;
use App\Http\Resources\DeliveryEvidenceResource;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\RouteStop;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function start(Request $request, RouteStop $stop)
    {
        $this->authorize('execute', $stop);

        $this->deliveries->startStop($stop);

        return response()->json(['message' => 'Entrega iniciada.']);
    }

    public function store(CreateDeliveryRequest $request, RouteStop $stop)
    {
        $this->authorize('execute', $stop);

        $delivery = $this->deliveries->createOrGet(
            $stop,
            $request->user(),
            $request->validated('client_uuid'),
            $request->validated('items'),
            $request->validated('notes'),
            $request->validated('latitude'),
            $request->validated('longitude'),
            $request->validated('delivered_at'),
        );

        return new DeliveryResource($delivery);
    }

    public function addEvidence(UploadEvidenceRequest $request, Delivery $delivery)
    {
        $this->authorize('execute', $delivery->routeStop);

        $evidence = $this->deliveries->addEvidence(
            $delivery,
            \App\Enums\EvidenceType::from($request->validated('type')),
            $request->file('file'),
        );

        return new DeliveryEvidenceResource($evidence);
    }

    /**
     * Sirve el archivo de evidencia de forma privada: solo administradores,
     * supervisores o el motorista dueño de la entrega pueden verlo
     * (sección 22: control de acceso a archivos).
     */
    public function showEvidence(Request $request, \App\Models\DeliveryEvidence $evidence)
    {
        $this->authorize('view', $evidence->delivery->routeStop);

        if (! Storage::disk('local')->exists($evidence->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($evidence->file_path);
    }
}
