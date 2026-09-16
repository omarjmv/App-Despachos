<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\EvidenceType;

class UploadEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(EvidenceType::class)],
            // Firmas/fotos ya comprimidas en el cliente (Documento 4 §4);
            // el límite del servidor es solo una red de seguridad.
            'file' => ['required', 'file', 'max:5120'],
        ];
    }
}
