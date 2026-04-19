<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAuthenticatedRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:64'],
            'device_token' => ['required', 'string', 'max:512'],
        ];
    }
}
