<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UpdatePackage;
use App\Services\UpdateSignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-15 更新签名验证 + 回滚机制 + 区域灰度发布 API
 */
class UpdateSignerController extends Controller
{
    public function __construct(
        private readonly UpdateSignerService $signer,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->signer->getDashboard(),
        ]);
    }

    /**
     * 对更新包签名
     */
    public function sign(Request $request, int $id): JsonResponse
    {
        $package = UpdatePackage::findOrFail($id);

        $algorithm = $request->input('algorithm', config('update-signer.signing.algorithm', 'ed25519'));

        $package = $this->signer->signPackage($package, $algorithm);

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.signed'),
            'data' => [
                'id' => $package->id,
                'signature' => $package->signature,
                'algorithm' => $package->sign_algorithm,
                'public_key_version' => $package->public_key_version,
            ],
        ]);
    }

    /**
     * 验证签名（公开，SDK调用）
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'update_package_id' => 'required|integer|exists:update_packages,id',
            'file_hash' => 'required|string|max:128',
            'signature' => 'nullable|string|max:256',
            'sdk_instance_id' => 'nullable|string|max:64',
        ]);

        $package = UpdatePackage::findOrFail($validated['update_package_id']);

        $result = $this->signer->verifySignature(
            $package,
            $validated['file_hash'],
            $validated['signature'] ?? null,
            $validated['sdk_instance_id'] ?? null,
        );

        return response()->json([
            'code' => $result['verified'] ? 0 : 1,
            'message' => $result['verified'] ? __('app.api.update_signer.verify_passed') : __('app.api.update_signer.verify_failed', ['error' => $result['error_message'] ?? __('app.api.update_signer.unknown_error')]),
            'data' => $result,
        ]);
    }

    /**
     * 获取公钥（公开，SDK拉取）
     */
    public function publicKey(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->signer->getPublicKey($request->input('version')),
        ]);
    }

    /**
     * 验证日志列表
     */
    public function verificationLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['verified', 'algorithm', 'update_package_id']);

        return response()->json([
            'code' => 0,
            'data' => $this->signer->getVerificationLogs($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 创建回滚
     */
    public function createRollback(Request $request, int $id): JsonResponse
    {
        $package = UpdatePackage::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'trigger_type' => 'nullable|string|in:manual,auto_crash,auto_failure,auto_timeout',
        ]);

        try {
            $rollback = $this->signer->rollback(
                $package,
                $validated['trigger_type'] ?? 'manual',
                $validated['reason'] ?? null,
                $request->user()?->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.rollback_created') . ($rollback->status === 'pending' ? __('app.api.update_signer.rollback_pending') : ''),
            'data' => $rollback,
        ]);
    }

    /**
     * 审批回滚
     */
    public function approveRollback(int $id): JsonResponse
    {
        $rollback = \App\Models\UpdateRollback::findOrFail($id);

        $this->signer->approveRollback($rollback, request()->user()?->id);

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.rollback_approved'),
            'data' => $rollback->fresh(),
        ]);
    }

    /**
     * 执行回滚
     */
    public function executeRollback(int $id): JsonResponse
    {
        $rollback = \App\Models\UpdateRollback::findOrFail($id);

        try {
            $rollback = $this->signer->executeRollback($rollback);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.rollback_executed'),
            'data' => $rollback,
        ]);
    }

    /**
     * 回滚列表
     */
    public function rollbacks(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'trigger_type']);

        return response()->json([
            'code' => 0,
            'data' => $this->signer->getRollbacks($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 创建灰度发布
     */
    public function createGrayRelease(Request $request, int $id): JsonResponse
    {
        $package = UpdatePackage::findOrFail($id);

        $validated = $request->validate([
            'strategy' => 'nullable|string|in:region,percentage,whitelist,tenant_tag',
            'current_stage' => 'nullable|string|in:canary,beta,wide,full',
            'current_percentage' => 'nullable|integer|min:0|max:100',
            'target_regions' => 'nullable|array',
            'target_regions.*' => 'string',
            'excluded_regions' => 'nullable|array',
            'excluded_regions.*' => 'string',
            'whitelist_tenants' => 'nullable|array',
            'whitelist_tenants.*' => 'integer',
            'blacklist_tenants' => 'nullable|array',
            'blacklist_tenants.*' => 'integer',
            'tenant_tags' => 'nullable|array',
        ]);

        $release = $this->signer->createGrayRelease($package, $validated);

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.canary_rule_created'),
            'data' => $release,
        ]);
    }

    /**
     * 启动灰度发布
     */
    public function startGrayRelease(int $id): JsonResponse
    {
        $release = \App\Models\UpdateGrayRelease::findOrFail($id);
        $release = $this->signer->startGrayRelease($release);

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.canary_started'),
            'data' => $release,
        ]);
    }

    /**
     * 推进灰度发布到下一阶段
     */
    public function advanceGrayRelease(int $id): JsonResponse
    {
        $release = \App\Models\UpdateGrayRelease::findOrFail($id);
        $release = $this->signer->advanceGrayRelease($release);

        if (!$release) {
            return response()->json(['code' => 1, 'message' => __('app.api.update_signer.cannot_advance')], 422);
        }

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.canary_advanced', ['stage' => $release->current_stage, 'percent' => $release->current_percentage]),
            'data' => $release,
        ]);
    }

    /**
     * 暂停灰度发布
     */
    public function pauseGrayRelease(int $id): JsonResponse
    {
        $release = \App\Models\UpdateGrayRelease::findOrFail($id);
        $release = $this->signer->pauseGrayRelease($release);

        return response()->json([
            'code' => 0,
            'message' => __('app.api.update_signer.canary_paused'),
            'data' => $release,
        ]);
    }

    /**
     * 灰度发布列表
     */
    public function grayReleases(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'strategy']);

        return response()->json([
            'code' => 0,
            'data' => $this->signer->getGrayReleases($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 检查更新资格（公开，SDK调用）
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'update_package_id' => 'required|integer|exists:update_packages,id',
            'region' => 'nullable|string|max:50',
            'tenant_id' => 'nullable|string|max:64',
        ]);

        $package = UpdatePackage::findOrFail($validated['update_package_id']);

        return response()->json([
            'code' => 0,
            'data' => $this->signer->isEligibleForUpdate(
                $package,
                $validated['region'] ?? null,
                $validated['tenant_id'] ?? null,
            ),
        ]);
    }
}
