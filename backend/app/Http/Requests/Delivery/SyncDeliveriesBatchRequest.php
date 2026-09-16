<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class SyncDeliveriesBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['nullable', 'string', 'max:255'],
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.route_stop_id' => ['required', 'integer'],
            'operations.*.client_uuid' => ['required', 'uuid'],
            'operations.*.notes' => ['nullable', 'string', 'max:1000'],
            'operations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'operations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'operations.*.delivered_at' => ['nullable', 'date'],
            'operations.*.items' => ['required', 'array', 'min:1'],
            'operations.*.items.*.dispatch_item_id' => ['required', 'integer'],
            'operations.*.items.*.quantity_delivered' => ['required', 'numeric', 'min:0'],
            'operations.*.items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'operations.*.items.*.rejection_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
