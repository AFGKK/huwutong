<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'type' => 'nullable|string|in:trial,standard,enterprise,development',
            'count' => 'nullable|integer|min:1|max:100',
            'expires_at' => 'nullable|date',
            'seats' => 'nullable|integer|min:1|max:10000',
            'max_devices' => 'nullable|integer|min:1|max:1000',
            'metadata' => 'nullable|json',
        ];
    }
}
