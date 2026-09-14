<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class AssignOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', \App\Models\Order::class);
    }

    public function rules(): array
    {
        return [
            'preparer_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
