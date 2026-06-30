<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\EdgeVerifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Edge 授权验证控制器
 *
 * 提供边缘授权验证的 API 端点
 * - Token 生成与管理
 * - 回源验证端点
 * - 吊销同步
 * - 部署信息
 *
 * @m3-53 EdgeVerifier
 */
class EdgeVerifierController extends Controller
{
    public function __construct(
        protected EdgeVerifierService $edgeVerifier,
    ) {}

    /**
     * 获取边缘验证概览仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', License::class);

        $cacheStatus = $this->edgeVerifier->getCacheStatus();
        $deployInfo = $this->edgeVerifier->getDeploymentInfo();

        return response()->json([
            'success' => true,
            'data' => [
                'cache' => $cacheStatus,
                'deployment' => $deployInfo,
                'config' => [
                    'token_ttl' => config('edge-verifier.token_ttl'),
                    'edge_cache_ttl' => config('edge-verifier.edge_cache_ttl'),
                    'fallback_timeout' => config('edge-verifier.fallback_timeout'),
                    'rate_limit' => config('edge-verifier.rate_limit_per_minute'),
                    'degraded_mode' => config('edge-verifier.degraded_mode'),
                    'has_secret' => !empty(config('edge-verifier.signing_secret')),
                    'key_version' => config('edge-verifier.key_version'),
                ],
            ],
        ]);
    }

    /**
     * 为指定 License 生成边缘 Token
     */
    public function generateToken(Request $request): JsonResponse
    {
        $this->authorize('create', License::class);

        $validated = $request->validate([
            'license_key' => ['required', 'string', 'exists:licenses,license_key'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
        ]);

        $license = License::where('license_key', $validated['license_key'])->firstOrFail();

        try {
            $result = $this->edgeVerifier->generateToken(
                $license,
                $validated['ttl'] ?? config('edge-verifier.token_ttl', 3600),
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $result['token'],
                    'expires_at' => $result['expires_at'],
                    'expires_in' => $result['expires_at'] - time(),
                    'license_key' => $license->license_key,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Edge token generation failed', [
                'license_key' => $validated['license_key'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token 生成失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 批量生成边缘 Token
     */
    public function batchGenerateTokens(Request $request): JsonResponse
    {
        $this->authorize('create', License::class);

        $validated = $request->validate([
            'license_keys' => ['required', 'array', 'max:100'],
            'license_keys.*' => ['required', 'string', 'exists:licenses,license_key'],
        ]);

        $result = $this->edgeVerifier->batchGenerateTokens($validated['license_keys']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 验证边缘 Token
     * 内部端点，供 Cloudflare Worker 回源使用
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required_without:license_key', 'string'],
            'license_key' => ['required_without:token', 'string'],
            'product_code' => ['nullable', 'string'],
        ]);

        $result = $this->edgeVerifier->verifyToken($validated['token'] ?? '');

        if ($result['valid']) {
            return response()->json([
                'valid' => true,
                'data' => $result['data'],
            ]);
        }

        return response()->json($result);
    }

    /**
     * 回源验证端点
     * Cloudflare Worker 回源时调用此端点
     */
    public function originVerify(Request $request): JsonResponse
    {
        // 验证是否来自 Cloudflare Worker
        $isEdgeRequest = $request->header('X-Edge-Verify') === 'true';

        $validated = $request->validate([
            'license_key' => ['required_without:token', 'string'],
            'token' => ['required_without:license_key', 'string'],
            'product_code' => ['nullable', 'string'],
        ]);

        $result = $this->edgeVerifier->originVerify($validated);

        return response()->json($result, $result['valid'] ? 200 : 200);
    }

    /**
     * 吊销 License 的边缘缓存
     */
    public function revokeCache(Request $request): JsonResponse
    {
        $this->authorize('update', License::class);

        $validated = $request->validate([
            'license_key' => ['required', 'string', 'exists:licenses,license_key'],
        ]);

        $this->edgeVerifier->revokeCache($validated['license_key']);

        return response()->json([
            'success' => true,
            'message' => "License {$validated['license_key']} 的边缘缓存已吊销",
        ]);
    }

    /**
     * 同步吊销列表
     */
    public function syncRevocationList(): JsonResponse
    {
        $this->authorize('update', License::class);

        $result = $this->edgeVerifier->syncRevocationList();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 获取 Token 信息（不解密）
     */
    public function tokenInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        // 简单解码查看 (不验证签名)
        $parts = explode('.', $validated['token']);
        if (count($parts) !== 3) {
            return response()->json([
                'success' => false,
                'message' => 'Token 格式无效',
            ], 400);
        }

        $payload = json_decode(base64_decode($parts[1]), true);

        return response()->json([
            'success' => true,
            'data' => [
                'header' => json_decode(base64_decode($parts[0]), true),
                'payload' => $payload,
                'expires_in' => isset($payload['exp']) ? $payload['exp'] - time() : null,
            ],
        ]);
    }

    /**
     * 获取部署指引
     */
    public function deploymentGuide(): JsonResponse
    {
        $this->authorize('viewAny', License::class);

        $deployInfo = $this->edgeVerifier->getDeploymentInfo();

        $guide = [
            'prerequisites' => [
                'Node.js >= 18',
                'Cloudflare 账户',
                'Wrangler CLI (npm install -g wrangler)',
            ],
            'steps' => [
                [
                    'step' => 1,
                    'title' => '配置 Cloudflare 凭证',
                    'command' => 'npx wrangler login',
                ],
                [
                    'step' => 2,
                    'title' => '设置环境变量',
                    'command' => "npx wrangler secret put EDGE_VERIFIER_SECRET\nnpx wrangler secret put ORIGIN_URL",
                    'note' => 'EDGE_VERIFIER_SECRET 需与 .env 中的 EDGE_VERIFIER_SECRET 一致',
                ],
                [
                    'step' => 3,
                    'title' => '创建 KV 命名空间',
                    'command' => 'npx wrangler kv:namespace create HWT_CACHE',
                    'note' => '将返回的 ID 填入 wrangler.toml',
                ],
                [
                    'step' => 4,
                    'title' => '部署 Worker',
                    'command' => 'cd deploy/cf-workers/edge-verifier && npx wrangler deploy',
                ],
                [
                    'step' => 5,
                    'title' => '配置路由',
                    'command' => 'npx wrangler routes add *.yourdomain.com/api/edge/*',
                ],
                [
                    'step' => 6,
                    'title' => '验证部署',
                    'command' => 'curl https://yourdomain.com/api/edge/health',
                ],
            ],
            'files' => $deployInfo,
            'worker_directory' => 'deploy/cf-workers/edge-verifier/',
        ];

        return response()->json([
            'success' => true,
            'data' => $guide,
        ]);
    }
}
