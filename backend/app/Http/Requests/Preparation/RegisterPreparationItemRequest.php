<?php

namespace App\Http\Requests\Preparation;

use App\Enums\DifferenceReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RegisterPreparationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer'],
            'quantity_prepared' => ['required', 'numeric', 'min:0'],
            'difference_reason' => ['nullable', new Enum(DifferenceReason::class)],
            'difference_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
