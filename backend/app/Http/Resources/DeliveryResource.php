<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'result' => $this->result->value,
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'delivered_at' => $this->delivered_at,
            'items' => DeliveryItemResource::collection($this->whenLoaded('items')),
            'evidence' => DeliveryEvidenceResource::collection($this->whenLoaded('evidence')),
        ];
    }
}
