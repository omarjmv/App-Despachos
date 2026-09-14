<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Dispatch::class);
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
