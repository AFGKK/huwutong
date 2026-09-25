<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 创建 License 请求。
 *
 * customer_id 允许为空：支持预生成 pending 库存 Key，后续再绑定客户。
 * 激活/发货业务流程应在业务层校验客户归属（见功能测试报告 P3-3）。
 */
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
            // 可空：允许创建未绑定客户的 pending License（预生成）
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

    public function attributes(): array
    {
        return [
            'product_id' => 'product_id',
            'customer_id' => 'customer_id',
        ];
    }
}
