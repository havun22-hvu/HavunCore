<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateProjectRequest extends FormRequest
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
            'project' => ['required', 'string', 'max:100', 'unique:vault_projects,project'],
            'secrets' => ['nullable', 'array'],
            'secrets.*' => ['string'],
            'configs' => ['nullable', 'array'],
            'configs.*' => ['string'],
        ];
    }
}
