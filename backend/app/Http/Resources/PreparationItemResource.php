<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreparationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orderItem = $this->orderItem;

        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'product' => new ProductResource($orderItem->product),
            'quantity_requested' => (float) $orderItem->quantity_requested,
            'quantity_prepared' => (float) $this->quantity_prepared,
            'difference' => (float) $orderItem->quantity_requested - (float) $this->quantity_prepared,
            'difference_reason' => $this->difference_reason?->value,
            'difference_notes' => $this->difference_notes,
        ];
    }
}
