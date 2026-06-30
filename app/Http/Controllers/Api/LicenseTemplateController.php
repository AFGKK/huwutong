<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LicenseTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LicenseTemplateController extends Controller
{
    /**
     * 模板列表
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LicenseTemplate::class);

        $query = LicenseTemplate::with('product:id,name');

        // 租户隔离
        if (!$request->user()->hasRole('super-admin')) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        // 筛选活跃模板
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('sort_order')->orderBy('name');

        $perPage = min((int) $request->input('per_page', 50), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 模板详情
     */
    public function show(LicenseTemplate $licenseTemplate): JsonResponse
    {
        $this->authorize('view', $licenseTemplate);
        $licenseTemplate->load('product:id,name');

        return ApiResponse::success($licenseTemplate);
    }

    /**
     * 创建模板
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LicenseTemplate::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'product_id' => 'nullable|integer|exists:products,id',
            'type' => 'nullable|string|in:trial,standard,enterprise,development',
            'seats' => 'nullable|integer|min:1|max:10000',
            'max_devices' => 'nullable|integer|min:1|max:10000',
            'expiry_days' => 'nullable|integer|min:1|max:36500',
            'metadata' => 'nullable|json',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $template = LicenseTemplate::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'type' => $data['type'] ?? 'standard',
            'seats' => $data['seats'] ?? 1,
            'max_devices' => $data['max_devices'] ?? 1,
            'expiry_days' => $data['expiry_days'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::created($template, '模板创建成功');
    }

    /**
     * 更新模板
     */
    public function update(Request $request, LicenseTemplate $licenseTemplate): JsonResponse
    {
        $this->authorize('update', $licenseTemplate);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'product_id' => 'nullable|integer|exists:products,id',
            'type' => 'sometimes|string|in:trial,standard,enterprise,development',
            'seats' => 'sometimes|integer|min:1|max:10000',
            'max_devices' => 'sometimes|integer|min:1|max:10000',
            'expiry_days' => 'nullable|integer|min:1|max:36500',
            'metadata' => 'nullable|json',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $licenseTemplate->update($validator->validated());

        return ApiResponse::success($licenseTemplate->fresh(), '模板更新成功');
    }

    /**
     * 删除模板
     */
    public function destroy(LicenseTemplate $licenseTemplate): JsonResponse
    {
        $this->authorize('delete', $licenseTemplate);

        $licenseTemplate->delete();

        return ApiResponse::success(null, '模板已删除');
    }

    /**
     * 切换模板启用状态
     */
    public function toggleActive(LicenseTemplate $licenseTemplate): JsonResponse
    {
        $this->authorize('update', $licenseTemplate);

        $licenseTemplate->is_active = !$licenseTemplate->is_active;
        $licenseTemplate->save();

        return ApiResponse::success(
            ['is_active' => $licenseTemplate->is_active],
            $licenseTemplate->is_active ? '模板已启用' : '模板已停用'
        );
    }
}
