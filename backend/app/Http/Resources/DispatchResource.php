<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DispatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order' => new OrderResource($this->whenLoaded('order')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'driver' => new UserResource($this->whenLoaded('driver')),
            'dispatched_by' => new UserResource($this->whenLoaded('dispatchedBy')),
            'dispatched_at' => $this->dispatched_at,
            'items' => DispatchItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
