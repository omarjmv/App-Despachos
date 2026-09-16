<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dispatchItem = $this->dispatchItem;
        $product = $dispatchItem->preparationItem->orderItem->product;

        return [
            'id' => $this->id,
            'product' => new ProductResource($product),
            'quantity_dispatched' => (float) $dispatchItem->quantity_dispatched,
            'quantity_delivered' => (float) $this->quantity_delivered,
            'quantity_rejected' => (float) $this->quantity_rejected,
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
