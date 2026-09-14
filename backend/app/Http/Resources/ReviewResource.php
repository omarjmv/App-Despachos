<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'preparation_id' => $this->preparation_id,
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'result' => $this->result->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at,
        ];
    }
}
