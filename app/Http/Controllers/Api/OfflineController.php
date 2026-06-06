<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\OfflineLicenseService;
use App\Services\OfflineVerifier;
use App\Models\OfflineCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OfflineController extends Controller
{
    public function __construct(
        protected OfflineLicenseService $offlineService,
        protected OfflineVerifier       $offlineVerifier,
    ) {}

    /**
     * 离线验证 — 客户端提交 .license 文件验证
     *
     * POST /api/offline/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'license_file' => 'required|string', // .license 文件内容（Base64）
        ]);

        $result = $this->offlineVerifier->verify(
            $request->input('license_file'),
            $request->ip(),
        );

        if (! $result->isValid) {
            return ApiResponse::error(
                $result->errorCode ?? 'OFFLINE_VERIFY_FAILED',
                $result->message,
                422,
                $result->meta ?? [],
            );
        }

        return ApiResponse::success(
            $result->toArray(),
            '离线验证通过',
        );
    }

    /**
     * 获取公钥（供客户端下载）
     *
     * GET /api/offline/public-key?key_version=2
     */
    public function publicKey(Request $request): JsonResponse
    {
        $keyVersion = $request->input('key_version');

        $result = $keyVersion
            ? $this->offlineVerifier->getPublicKey((int) $keyVersion)
            : $this->offlineVerifier->getPublicKey(
                OfflineCertificate::where('is_active', true)
                    ->where('is_revoked', false)
                    ->orderBy('key_version', 'desc')
                    ->value('key_version') ?? 1
            );

        if (! $result) {
            return ApiResponse::notFound('找不到对应的公钥');
        }

        return ApiResponse::success($result);
    }

    /**
     * 获取吊销列表（CRL）
     *
     * GET /api/offline/crl?since=1700000000
     */
    public function crl(Request $request): JsonResponse
    {
        $since = $request->input('since');

        $crl = $this->offlineVerifier->getCrl($since ? (int) $since : null);

        return ApiResponse::success($crl);
    }

    /**
     * 生成离线 License 文件（管理端）
     *
     * POST /api/offline/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
        ]);

        $license = \App\Models\License::with(['product', 'customer'])->findOrFail($request->input('license_id'));

        // 获取当前活跃密钥
        $certificate = OfflineCertificate::where('is_active', true)
            ->where('is_revoked', false)
            ->orderBy('key_version', 'desc')
            ->first();

        if (! $certificate) {
            return ApiResponse::error('NO_ACTIVE_CERTIFICATE', '没有活跃的离线签名证书', 500);
        }

        $keyPair = $this->offlineService->getActiveKeyPair();

        $result = $this->offlineService->generateLicenseFile(
            $license,
            $keyPair['private_key'],
            $certificate->public_key,
            $certificate->algorithm,
        );

        return ApiResponse::created($result, '离线 License 文件生成成功');
    }

    /**
     * 批量生成离线 License 文件（管理端）
     *
     * POST /api/offline/generate/batch
     */
    public function generateBatch(Request $request): JsonResponse
    {
        $request->validate([
            'license_ids' => 'required|array',
            'license_ids.*' => 'integer|exists:licenses,id',
        ]);

        $licenses = \App\Models\License::with(['product', 'customer'])
            ->whereIn('id', $request->input('license_ids'))
            ->get();

        $certificate = OfflineCertificate::where('is_active', true)
            ->where('is_revoked', false)
            ->orderBy('key_version', 'desc')
            ->first();

        if (! $certificate) {
            return ApiResponse::error('NO_ACTIVE_CERTIFICATE', '没有活跃的离线签名证书', 500);
        }

        $keyPair = $this->offlineService->getActiveKeyPair();

        $results = $this->offlineService->generateBatch(
            $licenses->all(),
            $keyPair['private_key'],
            $certificate->public_key,
        );

        return ApiResponse::created($results, '批量离线 License 文件生成成功');
    }

    /**
     * 吊销 License（加入 CRL）
     *
     * POST /api/offline/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);

        $this->offlineVerifier->revokeLicense(
            $request->input('license_key'),
            $request->input('reason', '管理员吊销'),
        );

        return ApiResponse::success(null, 'License 已加入离线吊销列表');
    }

    /**
     * 从 CRL 恢复 License
     *
     * POST /api/offline/restore
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
        ]);

        $this->offlineVerifier->restoreLicense($request->input('license_key'));

        return ApiResponse::success(null, 'License 已移出离线吊销列表');
    }

    /**
     * 初始化/轮换签名密钥（管理端）
     *
     * POST /api/offline/init-keys
     */
    public function initKeys(): JsonResponse
    {
        // 生成新密钥对
        $keyPair = $this->offlineService->generateKeyPair();

        // 计算下一个版本号
        $maxVersion = OfflineCertificate::max('key_version') ?? 0;
        $newVersion = $maxVersion + 1;

        // 停用旧密钥
        if ($maxVersion > 0) {
            OfflineCertificate::where('is_active', true)->update(['is_active' => false]);
        }

        // 保存新证书
        $certificate = OfflineCertificate::create([
            'tenant_id' => null, // 系统级
            'key_version' => $newVersion,
            'algorithm' => OfflineLicenseService::ALGORITHM_ED25519,
            'public_key' => $keyPair['public_key'],
            'seed_encrypted' => $keyPair['seed'], // 生产环境应加密存储
            'is_active' => true,
            'expires_at' => now()->addYear(),
        ]);

        // 清除缓存
        Cache::tags(['offline_cert'])->flush();

        return ApiResponse::created([
            'key_version' => $newVersion,
            'algorithm' => OfflineLicenseService::ALGORITHM_ED25519,
            'public_key' => $keyPair['public_key'],
        ], '签名密钥初始化成功');
    }
}
