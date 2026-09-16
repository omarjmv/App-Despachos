<?php

namespace App\Http\Requests\Routes;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stop_ids' => ['required', 'array', 'min:1'],
            'stop_ids.*' => ['integer', 'distinct'],
        ];
    }
}
