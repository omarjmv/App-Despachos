<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', \App\Models\Customer::class);
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('customers', 'code')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($customerId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
        ];
    }
}
