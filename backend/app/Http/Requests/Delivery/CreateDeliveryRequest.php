<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class CreateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivered_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.dispatch_item_id' => ['required', 'integer'],
            'items.*.quantity_delivered' => ['required', 'numeric', 'min:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
