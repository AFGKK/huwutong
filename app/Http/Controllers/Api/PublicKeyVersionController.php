<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\PublicKeyVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 公钥版本管理控制器 (M2-135)
 *
 * 公钥版本的 Web 管理接口，支持：
 * - 版本列表/详情
 * - 创建新版本（轮换）
 * - 吊销版本
 * - 签名验证测试
 * - 版本统计
 */
class PublicKeyVersionController extends Controller
{
    public function __construct(
        protected PublicKeyVersionService $keyVersionService,
    ) {}

    /**
     * 公钥版本列表
     */
    public function index(): JsonResponse
    {
        $versions = $this->keyVersionService->getAllVersions();

        return ApiResponse::success([
            'versions' => $versions,
            'compat_window_days' => PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS,
        ]);
    }

    /**
     * 公钥版本详情
     */
    public function show(int $keyVersion): JsonResponse
    {
        $detail = $this->keyVersionService->getVersionDetail($keyVersion);
        if (! $detail) {
            return ApiResponse::notFound('公钥版本不存在');
        }

        return ApiResponse::success($detail);
    }

    /**
     * 创建新公钥版本（轮换）
     *
     * POST /api/public-keys/versions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => 'required|string',
            'algorithm' => 'nullable|string|in:Ed25519,RSA-2048',
            'public_key_pem' => 'nullable|string',
        ]);

        $version = $this->keyVersionService->createVersion(
            $validated['public_key'],
            $validated['algorithm'] ?? 'Ed25519',
            $validated['public_key_pem'] ?? null,
        );

        return ApiResponse::created([
            'key_version' => $version->key_version,
            'algorithm' => $version->algorithm,
            'public_key' => $version->public_key,
            'expires_at' => $version->expires_at?->toIso8601String(),
            'compat_window_days' => PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS,
        ], '新公钥版本已创建，旧版本进入 ' . PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS . ' 天兼容窗口期');
    }

    /**
     * 吊销公钥版本
     */
    public function revoke(Request $request, int $keyVersion): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $ok = $this->keyVersionService->revokeVersion($keyVersion, $validated['reason']);
        if (! $ok) {
            return ApiResponse::notFound('公钥版本不存在');
        }

        return ApiResponse::success(null, '公钥版本已吊销');
    }

    /**
     * 签名验证测试
     */
    public function testSigning(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => 'required|string',
            'algorithm' => 'nullable|string|in:Ed25519,RSA-2048',
        ]);

        $result = $this->keyVersionService->testSigning(
            $validated['public_key'],
            $validated['algorithm'] ?? 'Ed25519',
        );

        return ApiResponse::success($result);
    }

    /**
     * 公钥版本统计
     */
    public function stats(): JsonResponse
    {
        $stats = $this->keyVersionService->getStats();

        return ApiResponse::success($stats);
    }

    /**
     * 检查是否需要轮换
     */
    public function rotationCheck(): JsonResponse
    {
        $result = $this->keyVersionService->checkRotationNeeded();

        return ApiResponse::success($result);
    }

    /**
     * 有效的公钥列表（供 SDK 客户端拉取）
     */
    public function validKeys(): JsonResponse
    {
        $keys = $this->keyVersionService->getValidVersions();

        return ApiResponse::success([
            'keys' => $keys,
            'compat_window_days' => PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS,
        ]);
    }
}
