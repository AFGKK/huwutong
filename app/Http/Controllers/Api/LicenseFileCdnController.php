<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseFileRecord;
use App\Models\PublicKeyVersion;
use App\Services\CdnDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LicenseFileCdnController extends Controller
{
    public function __construct(
        protected CdnDistributionService $cdnService,
    ) {}

    /**
     * 分发列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = LicenseFileRecord::with('license.product');

        // 搜索
        if ($search = $request->input('search')) {
            $query->whereHas('license', function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%");
            })->orWhere('original_filename', 'like', "%{$search}%");
        }

        // 状态筛选
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $records = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        $records->getCollection()->transform(function ($r) {
            return [
                'id' => $r->id,
                'license_key' => $r->license?->license_key ?? 'N/A',
                'product_name' => $r->license?->product?->name ?? 'N/A',
                'original_filename' => $r->original_filename,
                'file_size' => $r->file_size,
                'file_hash' => $r->file_hash,
                'signature' => $r->signature,
                'key_version' => $r->key_version,
                'algorithm' => $r->algorithm,
                'cdn_url' => $r->cdn_url,
                'status' => $r->status,
                'download_count' => $r->distributions()->count(),
                'created_at' => $r->created_at?->toIso8601String(),
                'expires_at' => $r->expires_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success($records);
    }

    /**
     * 生成并分发 License 文件
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
        ]);

        $license = License::findOrFail($validated['license_id']);

        try {
            $record = $this->cdnService->generateAndDistribute($license);
            return ApiResponse::created([
                'id' => $record->id,
                'license_key' => $license->license_key,
                'file_key' => $record->file_key,
                'file_size' => $record->file_size,
                'file_hash' => $record->file_hash,
                'cdn_url' => $record->cdn_url,
            ], 'License 文件生成并分发成功');
        } catch (\Throwable $e) {
            return ApiResponse::error('GENERATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 批量分发
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_ids' => 'required|array',
            'license_ids.*' => 'integer|exists:licenses,id',
        ]);

        $results = $this->cdnService->batchDistribute($validated['license_ids']);

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        return ApiResponse::success([
            'total' => count($results),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results,
        ], "分发完成：{$successCount} 成功，{$failCount} 失败");
    }

    /**
     * 公开下载端点 — 客户端下载 .license 文件
     */
    public function download(Request $request, string $licenseKey): \Illuminate\Http\Response|JsonResponse
    {
        try {
            $fileInfo = $this->cdnService->serveFile($licenseKey, $request);

            return response($fileInfo['file_content'], 200, [
                'Content-Type' => $fileInfo['mime_type'],
                'Content-Disposition' => 'attachment; filename="' . $fileInfo['original_filename'] . '"',
                'Content-Length' => strlen($fileInfo['file_content']),
                'X-File-Hash' => $fileInfo['file_hash'],
                'Cache-Control' => 'public, max-age=' . CdnDistributionService::CDN_CACHE_TTL,
            ]);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 404;
            return ApiResponse::error('FILE_NOT_FOUND', $e->getMessage(), $code);
        }
    }

    /**
     * 公钥版本列表 — 客户端 CDN 拉取
     */
    public function publicKeys(): JsonResponse
    {
        $keys = $this->cdnService->getPublicKeyList();

        return ApiResponse::success([
            'keys' => $keys,
            'compat_window_days' => CdnDistributionService::PUBLIC_KEY_COMPAT_WINDOW,
        ]);
    }

    /**
     * CRL 吊销列表 — 客户端拉取
     */
    public function crl(Request $request): JsonResponse
    {
        $since = $request->input('since');

        $query = \App\Models\CertificateRevocationList::query();

        if ($since) {
            $query->where('revoked_at', '>=', date('Y-m-d H:i:s', (int) $since));
        }

        $entries = $query->orderBy('revoked_at', 'desc')->get()->map(function ($e) {
            return [
                'license_key' => $e->license_key,
                'reason' => $e->reason,
                'revoked_at' => $e->revoked_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success([
            'entries' => $entries,
            'total' => count($entries),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * 吊销已分发的 License 文件
     */
    public function revoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);

        $this->cdnService->revokeFile($validated['license_key'], $validated['reason'] ?? null);

        return ApiResponse::success(null, 'License 文件已吊销并加入 CRL');
    }

    /**
     * 重新分发（更新）
     */
    public function redistribute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
        ]);

        $license = License::findOrFail($validated['license_id']);

        try {
            $record = $this->cdnService->redistribute($license);
            return ApiResponse::success([
                'id' => $record->id,
                'license_key' => $license->license_key,
                'cdn_url' => $record->cdn_url,
            ], 'License 文件已重新分发');
        } catch (\Throwable $e) {
            return ApiResponse::error('REDISTRIBUTE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 轮换公钥
     */
    public function rotateKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => 'required|string',
            'algorithm' => 'nullable|string|in:Ed25519,RSA-2048',
        ]);

        $key = $this->cdnService->rotatePublicKey(
            $validated['public_key'],
            $validated['algorithm'] ?? 'Ed25519'
        );

        return ApiResponse::created([
            'key_version' => $key->key_version,
            'algorithm' => $key->algorithm,
            'public_key' => $key->public_key,
            'expires_at' => $key->expires_at?->toIso8601String(),
            'compat_window_days' => CdnDistributionService::PUBLIC_KEY_COMPAT_WINDOW,
        ], '公钥版本已轮换，旧版本将在 ' . CdnDistributionService::PUBLIC_KEY_COMPAT_WINDOW . ' 天后过期');
    }

    /**
     * 分发统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getStats());
    }

    /**
     * 分发日志明细
     */
    public function logs(Request $request): JsonResponse
    {
        $query = \App\Models\CdnDistribution::with('fileRecord.license')
            ->orderBy('downloaded_at', 'desc');

        if ($recordId = $request->input('file_record_id')) {
            $query->where('license_file_record_id', $recordId);
        }

        $logs = $query->paginate($request->input('per_page', 30));

        $logs->getCollection()->transform(function ($l) {
            return [
                'id' => $l->id,
                'license_key' => $l->fileRecord?->license?->license_key ?? 'N/A',
                'client_ip' => $l->client_ip,
                'user_agent' => $l->user_agent,
                'country' => $l->country,
                'response_code' => $l->response_code,
                'bytes_served' => $l->bytes_served,
                'downloaded_at' => $l->downloaded_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success($logs);
    }
}
