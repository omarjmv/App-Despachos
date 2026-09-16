<?php

namespace App\Http\Requests\Routes;

use App\Models\RouteModel;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', RouteModel::class);
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'dispatch_ids' => ['required', 'array', 'min:1'],
            'dispatch_ids.*' => ['integer', 'distinct', 'exists:dispatches,id'],
        ];
    }
}
