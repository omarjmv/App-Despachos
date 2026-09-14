<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'assigned_to' => new UserResource($this->whenLoaded('assignee')),
            'order_date' => $this->order_date?->toDateString(),
            'required_date' => $this->required_date?->toDateString(),
            'status' => $this->status->value,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'total_requested' => $this->whenLoaded('items', fn () => (float) $this->items->sum('quantity_requested')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
