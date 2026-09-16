<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteStopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'status' => $this->status->value,
            'dispatch' => new DispatchResource($this->whenLoaded('dispatch')),
            'delivery' => new DeliveryResource($this->whenLoaded('delivery')),
        ];
    }
}
