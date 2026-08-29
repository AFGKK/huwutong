<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required_without:phone|nullable|email|unique:users,email',
            'phone' => ['required_without:email', 'nullable', 'string', 'regex:/^1[3-9]\d{9}$/', 'unique:users,phone'],
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
