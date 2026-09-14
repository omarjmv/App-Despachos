<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreparationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'prepared_by' => new UserResource($this->whenLoaded('preparedBy')),
            'status' => $this->status->value,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'total_requested' => $this->order->totalRequested(),
            'total_prepared' => $this->totalPrepared(),
            'items' => PreparationItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
