<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
            'settings' => $this->settings,
        ];
    }
}
