<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'nullable|integer|exists:products,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'type' => 'nullable|string|in:trial,standard,enterprise,development',
            'expires_at' => 'nullable|date',
            'seats' => 'nullable|integer|min:1|max:10000',
            'max_devices' => 'nullable|integer|min:1|max:1000',
            'metadata' => 'nullable|json',
        ];
    }
}
