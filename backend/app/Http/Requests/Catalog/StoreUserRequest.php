<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', \App\Models\User::class);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $passwordRules = $userId ? ['nullable'] : ['required'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email',
                Rule::unique('users', 'email')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [...$passwordRules, 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ];
    }
}
