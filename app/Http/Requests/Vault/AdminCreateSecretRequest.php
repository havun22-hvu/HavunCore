<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,array<int,string>|string>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:191', 'unique:vault_secrets,key'],
            'value' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ];
    }
}
