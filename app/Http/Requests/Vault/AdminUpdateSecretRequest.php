<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Partial-update: every field is optional, but when present it must
     * type-check (and `value` must not be blank — empty secrets are a
     * footgun, never a legitimate intent).
     *
     * @return array<string,array<int,string>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'string', 'min:1'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
