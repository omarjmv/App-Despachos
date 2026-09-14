<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DispatchItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preparationItem = $this->preparationItem;
        $orderItem = $preparationItem->orderItem;

        return [
            'id' => $this->id,
            'product' => new ProductResource($orderItem->product),
            'quantity_dispatched' => (float) $this->quantity_dispatched,
        ];
    }
}
