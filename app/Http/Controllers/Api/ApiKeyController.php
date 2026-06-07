<?php

namespace App\Http\Controllers\Api;

use App\Enums\ErrorCode;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiKeyAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * 获取当前用户的 API 密钥列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $keys = ApiKey::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($key) => $this->formatKey($key));

        $stats = [
            'total' => $keys->count(),
            'active' => $keys->where('is_active', true)->count(),
            'by_tier' => $keys->groupBy('tier')->map->count(),
            'by_permission' => $keys->groupBy('permissions')->map->count(),
        ];

        return ApiResponse::success([
            'keys' => $keys,
            'stats' => $stats,
            'max_keys' => 20,
        ]);
    }

    /**
     * 创建新的 API 密钥
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'permissions' => 'sometimes|in:read-only,read-write,admin',
            'tier' => 'sometimes|in:free,standard,enterprise,custom',
            'allowed_endpoints' => 'sometimes|nullable|array',
            'allowed_endpoints.*' => 'string',
            'allowed_methods' => 'sometimes|nullable|string',
            'rate_limit' => 'sometimes|nullable|integer|min:1|max:10000',
            'usage_quota' => 'sometimes|nullable|integer|min:1',
            'daily_quota' => 'sometimes|nullable|integer|min:1',
            'allowed_ip' => 'sometimes|nullable|ip',
            'allowed_ips' => 'sometimes|nullable|array',
            'allowed_ips.*' => 'ip',
            'allowed_referrers' => 'sometimes|nullable|array',
            'allowed_referrers.*' => 'string|max:200',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
            'metadata' => 'sometimes|nullable|array',
            'expires_at' => 'sometimes|nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;
        $data = $validator->validated();
        $tier = $data['tier'] ?? 'standard';

        // 检查密钥数量限制（按等级）
        if (! ApiKey::canCreateForTier($tier, $tenantId)) {
            $limits = ApiKey::TIER_LIMITS[$tier] ?? ApiKey::TIER_LIMITS['standard'];
            return ApiResponse::error(ErrorCode::MAX_KEYS_REACHED->value, "{$tier} 等级最多可创建 {$limits['max_keys']} 个 API 密钥", 422);
        }

        $keyId = 'ak_' . Str::random(32);
        $secret = Str::random(48);

        $data['tenant_id'] = $tenantId;
        $data['key_id'] = $keyId;
        $data['secret'] = Hash::make($secret);
        $data['is_active'] = true;
        $data['created_by'] = $request->user()->id;

        // 清理空值
        $data['allowed_ip'] = $data['allowed_ip'] ?? null;
        $data['allowed_ips'] = ! empty($data['allowed_ips']) ? $data['allowed_ips'] : null;
        $data['allowed_referrers'] = ! empty($data['allowed_referrers']) ? $data['allowed_referrers'] : null;
        $data['tags'] = ! empty($data['tags']) ? $data['tags'] : null;
        $data['metadata'] = ! empty($data['metadata']) ? $data['metadata'] : null;

        $key = ApiKey::create($data);

        // 审计日志
        $key->logAction('create', 'user', $request->user()->id, null, $data);

        return response()->json([
            'success' => true,
            'message' => 'API 密钥创建成功',
            'data' => array_merge($this->formatKey($key), [
                'secret' => $secret,
            ]),
        ], 201);
    }

    /**
     * 获取 API 密钥详情
     */
    public function show(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);
        return ApiResponse::success($this->formatKey($apiKey));
    }

    /**
     * 更新 API 密钥
     */
    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'permissions' => 'sometimes|in:read-only,read-write,admin',
            'tier' => 'sometimes|in:free,standard,enterprise,custom',
            'allowed_endpoints' => 'sometimes|nullable|array',
            'allowed_endpoints.*' => 'string',
            'allowed_methods' => 'sometimes|nullable|string',
            'rate_limit' => 'sometimes|nullable|integer|min:1|max:10000',
            'usage_quota' => 'sometimes|nullable|integer|min:1',
            'daily_quota' => 'sometimes|nullable|integer|min:1',
            'allowed_ip' => 'sometimes|nullable|ip',
            'allowed_ips' => 'sometimes|nullable|array',
            'allowed_ips.*' => 'ip',
            'allowed_referrers' => 'sometimes|nullable|array',
            'allowed_referrers.*' => 'string|max:200',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
            'metadata' => 'sometimes|nullable|array',
            'expires_at' => 'sometimes|nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $oldValues = $apiKey->toArray();
        $apiKey->update($validator->validated());

        // 审计日志
        $newValues = $apiKey->fresh()->toArray();
        $apiKey->logAction('update', 'user', $request->user()->id, $oldValues, $newValues);

        return ApiResponse::success($this->formatKey($apiKey->fresh()), 'API 密钥已更新');
    }

    /**
     * 删除 API 密钥（软删除）
     */
    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $apiKey->logAction('delete', 'user', $request->user()->id, $apiKey->toArray());
        $apiKey->delete();

        return ApiResponse::success(null, 'API 密钥已删除');
    }

    /**
     * 重新生成密钥
     */
    public function regenerate(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $newSecret = Str::random(48);
        $oldValues = $apiKey->toArray();
        $apiKey->secret = Hash::make($newSecret);
        $apiKey->rotated_at = now();
        $apiKey->save();

        $apiKey->logAction('regenerate', 'user', $request->user()->id, $oldValues, $apiKey->fresh()->toArray());

        return ApiResponse::success([
            'id' => $apiKey->id,
            'key_id' => $apiKey->key_id,
            'secret' => $newSecret,
            'rotated_at' => $apiKey->rotated_at,
        ], '密钥已重新生成');
    }

    /**
     * 切换启用状态
     */
    public function toggleActive(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $apiKey->is_active = ! $apiKey->is_active;
        $apiKey->save();

        $action = $apiKey->is_active ? '启用' : '禁用';
        $apiKey->logAction('toggle', 'user', $request->user()->id, ['is_active' => !$apiKey->is_active], ['is_active' => $apiKey->is_active]);

        return ApiResponse::success($this->formatKey($apiKey->fresh()), "API 密钥已{$action}");
    }

    /**
     * 获取密钥审计日志
     */
    public function auditLogs(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $logs = $apiKey->auditLogs()
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($logs);
    }

    /**
     * 获取密钥用量统计
     */
    public function usageStats(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $tierLimits = $apiKey->tierLimits();

        return ApiResponse::success([
            'usage_count' => $apiKey->usage_count,
            'usage_quota' => $apiKey->usage_quota,
            'daily_usage' => $apiKey->daily_usage,
            'daily_quota' => $apiKey->daily_quota,
            'daily_reset_at' => $apiKey->daily_reset_at,
            'usage_percent' => $apiKey->usage_quota ? round(($apiKey->usage_count / $apiKey->usage_quota) * 100, 1) : null,
            'daily_usage_percent' => $apiKey->daily_quota ? round(($apiKey->daily_usage / $apiKey->daily_quota) * 100, 1) : null,
            'tier_limits' => $tierLimits,
            'last_used_at' => $apiKey->last_used_at,
            'rotated_at' => $apiKey->rotated_at,
        ]);
    }

    /**
     * 获取当前用户的密钥用量概览
     */
    public function myUsageOverview(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $keys = ApiKey::where('tenant_id', $tenantId)->get();

        return ApiResponse::success([
            'total_keys' => $keys->count(),
            'active_keys' => $keys->where('is_active', true)->count(),
            'total_usage_count' => $keys->sum('usage_count'),
            'total_daily_usage' => $keys->sum('daily_usage'),
            'keys_near_quota' => $keys->filter(fn ($k) => $k->usage_quota && ($k->usage_count / $k->usage_quota) > 0.8)->count(),
            'keys_expiring_soon' => $keys->filter(fn ($k) => $k->expires_at && $k->expires_at->diffInDays(now()) <= 7)->count(),
            'keys_expired' => $keys->filter(fn ($k) => $k->expires_at && $k->expires_at->isPast())->count(),
        ]);
    }

    /**
     * 获取所有审计日志（管理）
     */
    public function allAuditLogs(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $logs = ApiKeyAuditLog::where('tenant_id', $tenantId)
            ->with('apiKey:id,key_id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 30));

        return ApiResponse::paginated($logs);
    }

    /**
     * 获取可用等级配置
     */
    public function tierConfig(): JsonResponse
    {
        return ApiResponse::success([
            'permissions' => collect(ApiKey::PERMISSIONS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
                'level' => ApiKey::PERMISSION_LEVEL_MAP[$value],
            ])->values(),
            'tiers' => collect(ApiKey::TIERS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
                'limits' => ApiKey::TIER_LIMITS[$value],
            ])->values(),
        ]);
    }

    // ─── 辅助方法 ──────────────────────────────────────────

    /**
     * 格式化密钥输出
     */
    protected function formatKey(ApiKey $key): array
    {
        return [
            'id' => $key->id,
            'key_id' => $key->key_id,
            'name' => $key->name,
            'description' => $key->description,
            'permissions' => $key->permissions,
            'tier' => $key->tier ?? 'standard',
            'allowed_endpoints' => $key->allowed_endpoints,
            'allowed_methods' => $key->allowed_methods,
            'rate_limit' => $key->rate_limit,
            'usage_quota' => $key->usage_quota,
            'usage_count' => $key->usage_count,
            'daily_quota' => $key->daily_quota,
            'daily_usage' => $key->daily_usage,
            'daily_reset_at' => $key->daily_reset_at,
            'allowed_ip' => $key->allowed_ip,
            'allowed_ips' => $key->allowed_ips,
            'allowed_referrers' => $key->allowed_referrers,
            'tags' => $key->tags,
            'metadata' => $key->metadata,
            'is_active' => $key->is_active,
            'last_used_at' => $key->last_used_at,
            'rotated_at' => $key->rotated_at,
            'expires_at' => $key->expires_at,
            'created_at' => $key->created_at,
            'updated_at' => $key->updated_at,
            'deleted_at' => $key->deleted_at,
            'created_by' => $key->created_by,
        ];
    }

    /**
     * 验证当前用户是否拥有此 API Key
     */
    protected function authorizeKey(Request $request, ApiKey $apiKey): void
    {
        $tenantId = $request->user()->tenant_id;

        if ($apiKey->tenant_id !== $tenantId) {
            abort(403, '无权操作此 API 密钥');
        }
    }
}
