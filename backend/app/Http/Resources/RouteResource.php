<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'driver' => new UserResource($this->whenLoaded('driver')),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'stops' => RouteStopResource::collection($this->whenLoaded('stops')),
        ];
    }
}
