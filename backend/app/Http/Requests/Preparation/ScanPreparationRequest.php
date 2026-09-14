<?php

namespace App\Http\Requests\Preparation;

use Illuminate\Foundation\Http\FormRequest;

class ScanPreparationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string'],
        ];
    }
}
