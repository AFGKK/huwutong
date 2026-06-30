<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomFieldDefinition;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 自定义字段管理（M3-46）
 *
 * 支持 License/Customer/Product 多态自定义字段
 */
class CustomFieldController extends Controller
{
    public function __construct(
        protected CustomFieldService $customFieldService,
    ) {}

    // ═══════════════════════════════════════════
    // 字段定义 CRUD
    // ═══════════════════════════════════════════

    /**
     * 获取字段定义列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $isSuperAdmin = $request->user()->hasRole('super-admin');

        $query = CustomFieldDefinition::orderBy('sort_order')->orderBy('name');

        if (!$isSuperAdmin && $tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenantId);
            });
        }

        // 按目标实体筛选
        if ($request->filled('applies_to')) {
            $query->whereJsonContains('applies_to', $request->applies_to);
        }

        $fields = $query->get();

        return ApiResponse::success($fields);
    }

    /**
     * 创建字段定义
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'field_type' => 'required|string|in:' . implode(',', CustomFieldService::FIELD_TYPES),
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'applies_to' => 'nullable|array',
            'applies_to.*' => 'string|in:' . implode(',', CustomFieldService::ENTITY_TYPES),
            'placeholder' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'group' => 'nullable|string|max:100',
            'default_value' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $tenantId = $request->user()->tenant_id;

        $field = $this->customFieldService->createDefinition($validator->validated(), $tenantId);

        return ApiResponse::created($field, '自定义字段创建成功');
    }

    /**
     * 更新字段定义
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $field = CustomFieldDefinition::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'field_type' => 'sometimes|string|in:' . implode(',', CustomFieldService::FIELD_TYPES),
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'applies_to' => 'nullable|array',
            'applies_to.*' => 'string|in:' . implode(',', CustomFieldService::ENTITY_TYPES),
            'placeholder' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'group' => 'nullable|string|max:100',
            'default_value' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $field = $this->customFieldService->updateDefinition($field, $validator->validated());

        return ApiResponse::success($field, '自定义字段已更新');
    }

    /**
     * 删除字段定义
     */
    public function destroy(int $id): JsonResponse
    {
        $field = CustomFieldDefinition::findOrFail($id);
        $this->customFieldService->deleteDefinition($field);

        return ApiResponse::success(null, '自定义字段已删除');
    }

    // ═══════════════════════════════════════════
    // License 自定义字段值
    // ═══════════════════════════════════════════

    public function licenseValues(License $license): JsonResponse
    {
        $values = $this->customFieldService->getValues($license);
        return ApiResponse::success($values);
    }

    public function updateLicenseValues(Request $request, License $license): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'values' => 'required|array',
            'values.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        try {
            $values = $this->customFieldService->updateValues($license, $request->input('values'));
            return ApiResponse::success($values, '自定义字段值已更新');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    // ═══════════════════════════════════════════
    // 客户自定义字段值
    // ═══════════════════════════════════════════

    public function customerValues(Customer $customer): JsonResponse
    {
        $values = $this->customFieldService->getValues($customer);
        return ApiResponse::success($values);
    }

    public function updateCustomerValues(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'values' => 'required|array',
            'values.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        try {
            $values = $this->customFieldService->updateValues($customer, $request->input('values'));
            return ApiResponse::success($values, '客户自定义字段值已更新');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    // ═══════════════════════════════════════════
    // 产品自定义字段值
    // ═══════════════════════════════════════════

    public function productValues(Product $product): JsonResponse
    {
        $values = $this->customFieldService->getValues($product);
        return ApiResponse::success($values);
    }

    public function updateProductValues(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'values' => 'required|array',
            'values.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        try {
            $values = $this->customFieldService->updateValues($product, $request->input('values'));
            return ApiResponse::success($values, '产品自定义字段值已更新');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    // ═══════════════════════════════════════════
    // 元数据
    // ═══════════════════════════════════════════

    /**
     * 获取元数据（字段类型/实体类型选项）
     */
    public function metadata(): JsonResponse
    {
        return ApiResponse::success([
            'field_types' => $this->customFieldService->fieldTypeOptions(),
            'entity_types' => $this->customFieldService->entityTypeOptions(),
        ]);
    }
}
