<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', \App\Models\Product::class);
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($productId),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
