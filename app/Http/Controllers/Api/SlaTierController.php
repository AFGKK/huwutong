<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSlaAssignment;
use App\Models\SlaAuditEvent;
use App\Models\SlaTier;
use App\Services\SlaTierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlaTierController extends Controller
{
    public function __construct(
        protected SlaTierService $slaTierService,
    ) {}

    /**
     * 初始化默认 SLA 等级
     */
    public function initialize(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $this->slaTierService->initializeDefaults($tenantId);

        $tiers = SlaTier::where('tenant_id', $tenantId)->orderByDesc('priority')->get();

        return response()->json([
            'message' => 'SLA 等级已初始化',
            'data' => $tiers,
        ]);
    }

    /**
     * 获取所有 SLA 等级（含统计）
     */
    public function tiers(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $tiers = $this->slaTierService->getAllTiersWithStats($tenantId);

        return response()->json(['data' => $tiers]);
    }

    /**
     * 创建/更新 SLA 等级
     */
    public function upsertTier(Request $request, ?int $id = null): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $rules = [
            'slug' => "required|string|max:50|unique:sla_tiers,slug," . ($id ?? 'NULL') . ",id,tenant_id,{$tenantId}",
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'priority' => 'integer|min:0',
            'is_default' => 'boolean',
            'api_rate_limit' => 'integer|min:1',
            'api_burst_limit' => 'integer|min:1',
            'api_concurrent_limit' => 'integer|min:1',
            'verify_rate_limit' => 'integer|min:1',
            'verify_timeout_seconds' => 'integer|min:1|max:60',
            'max_active_licenses' => 'integer|min:0',
            'max_devices_per_license' => 'integer|min:0',
            'sla_response_hours' => 'integer|min:1',
            'sla_resolution_hours' => 'integer|min:1',
            'support_priority_queue' => 'boolean',
            'support_dedicated_manager' => 'boolean',
            'support_phone' => 'boolean',
            'support_24_7' => 'boolean',
            'audit_retention_days' => 'integer|min:30',
            'require_mfa' => 'boolean',
            'allowed_ip_ranges' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['tenant_id'] = $tenantId;

        // 如果设为默认，清除其他默认
        if (!empty($data['is_default'])) {
            SlaTier::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        if ($id) {
            $tier = SlaTier::findOrFail($id);
            $tier->update($data);
            $message = 'SLA 等级已更新';
        } else {
            $tier = SlaTier::create($data);
            $message = 'SLA 等级已创建';
        }

        return response()->json(['message' => $message, 'data' => $tier->fresh()]);
    }

    /**
     * 删除 SLA 等级
     */
    public function deleteTier(int $id): JsonResponse
    {
        $tier = SlaTier::findOrFail($id);

        if ($tier->is_default) {
            return response()->json(['error' => '不能删除默认 SLA 等级'], 400);
        }

        // 清除关联的自定义分配
        CustomerSlaAssignment::where('sla_tier_id', $id)->delete();
        $tier->delete();

        return response()->json(['message' => 'SLA 等级已删除']);
    }

    /**
     * 获取客户当前 SLA 等级
     */
    public function customerTier(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $tier = $this->slaTierService->getTierForCustomer($customer);

        // 获取分配信息
        $assignment = CustomerSlaAssignment::where('customer_id', $customerId)->first();

        return response()->json([
            'data' => [
                'tier' => $tier,
                'assignment' => $assignment,
                'is_custom' => $assignment !== null,
            ],
        ]);
    }

    /**
     * 为客户分配 SLA 等级
     */
    public function assignTier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'sla_tier_id' => 'required|exists:sla_tiers,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $customer = Customer::findOrFail($data['customer_id']);
        $tier = SlaTier::findOrFail($data['sla_tier_id']);

        $assignment = $this->slaTierService->assignTierToCustomer(
            $customer,
            $tier,
            $data['expires_at'] ?? null,
        );

        return response()->json([
            'message' => "SLA 等级 {$tier->name} 已分配给客户",
            'data' => $assignment,
        ]);
    }

    /**
     * 恢复客户默认 SLA
     */
    public function resetTier(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $this->slaTierService->resetToDefault($customer);

        return response()->json(['message' => 'SLA 等级已恢复默认']);
    }

    /**
     * 获取 SLA 审计日志
     */
    public function auditLog(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = (int) $request->input('per_page', 20);
        $customerId = $request->input('customer_id');

        $query = SlaAuditEvent::where('tenant_id', $tenantId)
            ->with('customer.user:id,name,email')
            ->with('slaTier:id,name,slug')
            ->orderByDesc('created_at');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * 处理过期分配（定时任务接口 / 手动触发）
     */
    public function processExpired(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $count = $this->slaTierService->processExpiredAssignments($tenantId);

        return response()->json(['message' => "已处理 {$count} 条过期分配"]);
    }
}
