<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GenerateQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Device-tracking metadata only — kept optional to stay backward compatible
     * with mobile clients that omit them.
     *
     * @return array<string,array<int,string>|string>
     */
    public function rules(): array
    {
        return [
            'browser' => ['nullable', 'string', 'max:100'],
            'os' => ['nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
