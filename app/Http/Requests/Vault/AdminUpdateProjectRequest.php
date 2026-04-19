<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateProjectRequest extends FormRequest
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
            'secrets' => ['sometimes', 'array'],
            'secrets.*' => ['string'],
            'configs' => ['sometimes', 'array'],
            'configs.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
