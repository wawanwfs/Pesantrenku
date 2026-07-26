<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string', 'max:64'],
            'device_id' => [$this->is('api/*') ? 'required' : 'nullable', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'device_name' => ['nullable', 'string', 'max:190'],
        ];
    }
}
