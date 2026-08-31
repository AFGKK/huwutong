<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LegalConsent;
use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 隐私协议/服务条款管理
 *
 * 管理员可创建、编辑、发布协议版本。
 * 用户需确认最新版本后方可继续使用。
 */
class LegalConsentController extends Controller
{
    /**
     * 获取所有协议版本（管理端）
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type'); // privacy_policy | terms_of_service
        $query = LegalConsent::query();

        if ($type) {
            $query->where('type', $type);
        }

        $consents = $query->latest()->paginate($request->input('per_page', 20));

        return ApiResponse::success($consents);
    }

    /**
     * 获取单个协议详情
     */
    public function show(LegalConsent $legalConsent): JsonResponse
    {
        return ApiResponse::success($legalConsent);
    }

    /**
     * 创建新协议版本
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:privacy_policy,terms_of_service',
            'version' => 'required|string|max:50',
            'content' => 'required|string',
            'effective_at' => 'nullable|date',
        ]);

        $consent = LegalConsent::create([
            'type' => $validated['type'],
            'version' => $validated['version'],
            'content' => $validated['content'],
            'is_current' => false, // 先保存，发布时设为 current
            'effective_at' => $validated['effective_at'] ?? now(),
        ]);

        return ApiResponse::success($consent, __("app.legal_consent.msg_f8629866"));
    }

    /**
     * 更新协议版本（仅草稿状态可更新）
     */
    public function update(Request $request, LegalConsent $legalConsent): JsonResponse
    {
        if ($legalConsent->is_current) {
            return ApiResponse::error('CANNOT_UPDATE_CURRENT', __("app.legal_consent.msg_f789c20d"), 422);
        }

        $validated = $request->validate([
            'content' => 'required|string',
            'version' => 'nullable|string|max:50',
            'effective_at' => 'nullable|date',
        ]);

        $legalConsent->update($validated);

        return ApiResponse::success($legalConsent, __("app.legal_consent.msg_34397cfe"));
    }

    /**
     * 发布协议（设为当前版本，旧版本自动失效）
     */
    public function publish(Request $request, LegalConsent $legalConsent): JsonResponse
    {
        // 同类型的所有版本设为非当前
        LegalConsent::where('type', $legalConsent->type)
            ->where('id', '!=', $legalConsent->id)
            ->update(['is_current' => false]);

        $legalConsent->update([
            'is_current' => true,
            'effective_at' => $request->input('effective_at') ?? now(),
        ]);

        // 审计日志
        app(\App\Services\AuditService::class)->log(
            action: 'legal_consent_published',
            description: "管理员 {$request->user()->name} 发布了 {$legalConsent->type} v{$legalConsent->version}",
            userId: $request->user()->id,
            payload: ['legal_consent_id' => $legalConsent->id, 'type' => $legalConsent->type, 'version' => $legalConsent->version],
        );

        return ApiResponse::success($legalConsent, __("app.legal_consent.msg_4d7a1302"));
    }

    /**
     * 获取协议确认记录
     */
    public function consentLogs(Request $request): JsonResponse
    {
        $query = UserConsent::with('user')
            ->when($request->legal_consent_id, fn($q, $v) => $q->where('legal_consent_id', $v))
            ->latest();

        $logs = $query->paginate($request->input('per_page', 20));

        return ApiResponse::success($logs);
    }

    /**
     * 获取当前生效的协议（公开接口，无需认证）
     */
    public function current(Request $request): JsonResponse
    {
        $type = $request->input('type', 'privacy_policy');
        $consent = LegalConsent::getCurrent($type);

        if (!$consent) {
            return ApiResponse::notFound(__("app.legal_consent.msg_59e3ec30"));
        }

        return ApiResponse::success($consent);
    }
}
