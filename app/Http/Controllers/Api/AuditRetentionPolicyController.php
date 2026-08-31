<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditRetentionPolicy;
use App\Models\Log;
use App\Support\DbSql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditRetentionPolicyController extends Controller
{
    /**
     * 获取保留策略列表
     *
     * GET /api/admin/audit-retention-policies
     */
    public function index(): JsonResponse
    {
        $policies = AuditRetentionPolicy::orderBy('type')->get();

        // 合并配置默认值
        $defaults = config('audit.retention_days', [
            'audit' => 365, 'security' => 365, 'error' => 180, 'system' => 90,
        ]);

        $result = [];
        foreach ($defaults as $type => $defaultDays) {
            $dbPolicy = $policies->firstWhere('type', $type);
            $result[] = [
                'id' => $dbPolicy?->id,
                'type' => $type,
                'type_label' => __('audit.type.' . $type, $type),
                'retention_days' => $dbPolicy?->retention_days ?? $defaultDays,
                'is_active' => $dbPolicy?->is_active ?? true,
                'is_custom' => $dbPolicy !== null,
                'description' => $dbPolicy?->description,
                'log_count' => Log::ofType($type)->count(),
                'oldest_log_date' => Log::ofType($type)->orderBy('created_at')->value('created_at'),
            ];
        }

        return ApiResponse::success($result);
    }

    /**
     * 创建或更新保留策略
     *
     * POST /api/admin/audit-retention-policies
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:audit,security,error,system',
            'retention_days' => 'required|integer|min:1|max:3650',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $policy = AuditRetentionPolicy::updateOrCreate(
            ['type' => $data['type']],
            [
                'retention_days' => $data['retention_days'],
                'is_active' => true,
                'description' => $data['description'] ?? null,
            ]
        );

        return ApiResponse::success(
            $policy->fresh(),
            '保留策略已保存（' . __('audit.type.' . $data['type'], $data['type']) . '：' . $data['retention_days'] . ' 天）'
        );
    }

    /**
     * 更新策略
     *
     * PUT /api/admin/audit-retention-policies/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $policy = AuditRetentionPolicy::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'retention_days' => 'sometimes|integer|min:1|max:3650',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $policy->update($validator->validated());

        return ApiResponse::success($policy->fresh(), __('app.audit_retention_policy.policy_updated'));
    }

    /**
     * 删除自定义策略（恢复为默认值）
     *
     * DELETE /api/admin/audit-retention-policies/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $policy = AuditRetentionPolicy::findOrFail($id);
        $type = $policy->type;
        $policy->delete();

        return ApiResponse::success(null, __("app.audit_retention_policy.msg_8bdc0563") . __('audit.type.' . $type, $type) . '）');
    }

    /**
     * 执行手动清理预览
     *
     * POST /api/admin/audit-retention-policies/preview-prune
     */
    public function previewPrune(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $days = AuditRetentionPolicy::getEffectiveDays($type ?? 'audit');

        $query = Log::query();
        if ($type) {
            $query->ofType($type);
        }

        $cutoff = now()->subDays($days);
        $count = (clone $query)->where('created_at', '<', $cutoff)->count();
        $oldest = (clone $query)->orderBy('created_at')->value('created_at');

        return ApiResponse::success([
            'type' => $type ?? 'all',
            'type_label' => $type ? __('audit.type.' . $type, $type) : '全部',
            'retention_days' => $days,
            'cutoff_date' => $cutoff->toDateString(),
            'to_prune' => $count,
            'oldest_log_date' => $oldest,
        ]);
    }

    /**
     * 获取审计日志统计概览（含保留策略信息）
     *
     * GET /api/admin/audit-retention-policies/overview
     */
    public function overview(): JsonResponse
    {
        $totalLogs = Log::count();
        $types = ['audit', 'security', 'error', 'system'];

        $byType = [];
        foreach ($types as $type) {
            $count = Log::ofType($type)->count();
            $oldest = Log::ofType($type)->orderBy('created_at')->value('created_at');
            $newest = Log::ofType($type)->latest()->value('created_at');

            $byType[] = [
                'type' => $type,
                'label' => __('audit.type.' . $type, $type),
                'count' => $count,
                'oldest' => $oldest,
                'newest' => $newest,
                'retention_days' => AuditRetentionPolicy::getEffectiveDays($type),
                'estimated_prune' => $count > 0 && $oldest
                    ? Log::ofType($type)->where('created_at', '<', now()->subDays(AuditRetentionPolicy::getEffectiveDays($type)))->count()
                    : 0,
            ];
        }

        // 近30天数据量
        $recent30d = Log::where('created_at', '>=', now()->subDays(30))->count();

        // 存储占用估算
        $estimatedMb = DbSql::estimateTableSizeMb((new Log)->getTable());

        return ApiResponse::success([
            'total' => $totalLogs,
            'by_type' => $byType,
            'recent_30d' => $recent30d,
            'estimated_storage_mb' => $estimatedMb,
            'by_date' => Log::selectRaw('DATE(created_at) as date, count(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }
}
