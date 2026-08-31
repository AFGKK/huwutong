<?php

namespace App\Services;

use App\Models\UpdatePackage;
use App\Models\UpdatePackageDownload;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 更新包 CDN 分发服务 (M2-69)
 *
 * 提供 CDN 缓存刷新、分发统计、带宽监控、断点续传支持
 */
class UpdateCdnService
{
    /** 仪表盘统计 */
    public function getDashboard(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $totalPackages = UpdatePackage::count();
        $publishedPackages = UpdatePackage::where('status', 'published')->count();
        $totalSize = UpdatePackage::where('status', 'published')->sum('file_size');

        // 本月下载量
        $monthlyDownloads = UpdatePackageDownload::where('created_at', '>=', $monthStart)->count();
        $totalDownloads = UpdatePackageDownload::count();

        // 本月带宽（预估）
        $monthlyDownloadsWithSize = UpdatePackageDownload::where('created_at', '>=', $monthStart)
            ->join('update_packages', 'update_package_downloads.update_package_id', '=', 'update_packages.id')
            ->sum('update_packages.file_size');

        $totalDownloadSize = UpdatePackageDownload::join('update_packages', 'update_package_downloads.update_package_id', '=', 'update_packages.id')
            ->sum('update_packages.file_size');

        // 地区分布
        $regionStats = UpdatePackageDownload::selectRaw("
            CASE
                WHEN client_ip IS NULL THEN 'unknown'
                ELSE SUBSTRING(client_ip, 1, GREATEST(LOCATE('.', client_ip) - 1, 0))
            END as region_prefix,
            COUNT(*) as count
        ")->groupBy('region_prefix')->orderByDesc('count')->limit(10)->get();

        // 热门下载 Top 10
        $topPackages = UpdatePackageDownload::selectRaw('update_package_id, COUNT(*) as downloads')
            ->groupBy('update_package_id')
            ->orderByDesc('downloads')
            ->limit(10)
            ->with('package.product')
            ->get()
            ->map(fn($item) => [
                'package_id' => $item->update_package_id,
                'version' => $item->package?->version,
                'product' => $item->package?->product?->name,
                'downloads' => $item->downloads,
            ]);

        // 每日下载趋势（近 30 天）
        $dailyTrend = UpdatePackageDownload::where('created_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        return compact(
            'totalPackages', 'publishedPackages', 'totalSize',
            'monthlyDownloads', 'totalDownloads',
            'monthlyDownloadSize', 'totalDownloadSize',
            'regionStats', 'topPackages', 'dailyTrend',
        );
    }

    /** 刷新 CDN 缓存 */
    public function purgeCache(string $url = null, UpdatePackage $package = null): array
    {
        $results = ['success' => true, 'purged' => [], 'errors' => []];

        // 如果指定了包则清除该包的 CDN 缓存
        if ($package) {
            $urls = $this->getPackageUrls($package);
        } elseif ($url) {
            $urls = [$url];
        } else {
            return ['success' => false, 'message' => __('app.common.specify_url_or_package_to_clear')];
        }

        $provider = config('update-cdn.cdn.provider', 'cloudflare');

        foreach ($urls as $purgeUrl) {
            try {
                $this->purgeSingleUrl($provider, $purgeUrl);
                $results['purged'][] = $purgeUrl;
            } catch (\Throwable $e) {
                $results['errors'][] = ['url' => $purgeUrl, 'error' => $e->getMessage()];
                Log::error("UpdateCdn: purge failed for {$purgeUrl}", ['error' => $e->getMessage()]);
            }
        }

        if (!empty($results['errors'])) {
            $results['success'] = false;
        }

        return $results;
    }

    /** 发布时自动刷新 CDN */
    public function purgeOnPublish(UpdatePackage $package): void
    {
        if (!config('update-cdn.purge.on_publish')) {
            return;
        }
        $this->purgeCache(null, $package);
    }

    /** 废弃时刷新 CDN */
    public function purgeOnDeprecate(UpdatePackage $package): void
    {
        if (!config('update-cdn.purge.on_deprecate')) {
            return;
        }
        $this->purgeCache(null, $package);
    }

    /** 获取包的 CDN URL 列表 */
    public function getPackageUrls(UpdatePackage $package): array
    {
        $baseUrl = config('update-cdn.cdn.base_url', 'https://cdn.huwutong.com');
        $path = $package->file_path;

        $urls = ["{$baseUrl}/{$path}"];

        // 如果有分块校验和，生成分块 URL
        if (!empty($package->checksums)) {
            foreach ($package->checksums as $chunk) {
                $urls[] = "{$baseUrl}/{$path}?chunk={$chunk['index']}";
            }
        }

        return $urls;
    }

    /** 获取分块信息（断点续传） */
    public function getChunkInfo(UpdatePackage $package): array
    {
        $chunks = $package->checksums ?? [];
        $chunkSize = config('update-cdn.distribution.chunk_size', 1048576);

        return [
            'chunk_size' => $chunkSize,
            'total_chunks' => count($chunks),
            'file_size' => $package->file_size,
            'file_hash' => $package->file_hash,
            'chunks' => $chunks,
            'resume_enabled' => config('update-cdn.distribution.resume_enabled', true),
        ];
    }

    /** 获取 CDN 配置信息 */
    public function getCdnConfig(): array
    {
        return [
            'enabled' => config('update-cdn.cdn.enabled'),
            'provider' => config('update-cdn.cdn.provider'),
            'base_url' => config('update-cdn.cdn.base_url'),
            'path_prefix' => config('update-cdn.cdn.path_prefix'),
            'cache_ttl' => config('update-cdn.cdn.cache_ttl'),
            'signed_url_ttl' => config('update-cdn.cdn.signed_url_ttl'),
            'purge_enabled' => config('update-cdn.purge.enabled'),
            'resume_enabled' => config('update-cdn.distribution.resume_enabled'),
            'regions' => config('update-cdn.regions.available'),
        ];
    }

    /** 下载日志列表 */
    public function getDownloadLogs(array $params = [])
    {
        $query = UpdatePackageDownload::with('package.product');

        if (!empty($params['package_id'])) {
            $query->where('update_package_id', $params['package_id']);
        }
        if (!empty($params['date_from'])) {
            $query->where('created_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->where('created_at', '<=', $params['date_to'] . ' 23:59:59');
        }
        if (!empty($params['ip'])) {
            $query->where('client_ip', $params['ip']);
        }

        $query->orderByDesc('created_at');
        $perPage = min((int)($params['per_page'] ?? 25), 100);
        return $query->paginate($perPage);
    }

    /** 带宽使用统计 */
    public function getBandwidthStats(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $monthlyBytes = UpdatePackageDownload::where('created_at', '>=', $monthStart)
            ->join('update_packages', 'update_package_downloads.update_package_id', '=', 'update_packages.id')
            ->sum('update_packages.file_size');

        $totalBytes = UpdatePackageDownload::join('update_packages', 'update_package_downloads.update_package_id', '=', 'update_packages.id')
            ->sum('update_packages.file_size');

        $warningThreshold = config('update-cdn.monitoring.bandwidth_warning_threshold_mb', 100000) * 1024 * 1024;
        $criticalThreshold = config('update-cdn.monitoring.bandwidth_critical_threshold_mb', 500000) * 1024 * 1024;

        $level = 'normal';
        if ($monthlyBytes >= $criticalThreshold) {
            $level = 'critical';
        } elseif ($monthlyBytes >= $warningThreshold) {
            $level = 'warning';
        }

        // 近 7 天每日带宽
        $dailyBW = UpdatePackageDownload::where('created_at', '>=', $now->copy()->subDays(7))
            ->join('update_packages', 'update_package_downloads.update_package_id', '=', 'update_packages.id')
            ->selectRaw('DATE(update_package_downloads.created_at) as date, SUM(update_packages.file_size) as bytes, COUNT(*) as downloads')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'monthly_bytes' => $monthlyBytes,
            'monthly_mb' => round($monthlyBytes / (1024 * 1024), 2),
            'monthly_gb' => round($monthlyBytes / (1024 * 1024 * 1024), 2),
            'total_bytes' => $totalBytes,
            'total_gb' => round($totalBytes / (1024 * 1024 * 1024), 2),
            'level' => $level,
            'daily_bandwidth' => $dailyBW,
            'warning_threshold_mb' => $warningThreshold / (1024 * 1024),
            'critical_threshold_mb' => $criticalThreshold / (1024 * 1024),
        ];
    }

    /** 检查并发下载限制 */
    public function checkConcurrentLimit(string $ip): bool
    {
        $maxConcurrent = config('update-cdn.distribution.concurrent_downloads', 10);
        $key = "update_cdn:concurrent:{$ip}";

        $current = Cache::get($key, 0);
        return $current < $maxConcurrent;
    }

    /** 检查下载频率限制 */
    public function checkRateLimit(string $ip): bool
    {
        $maxPerMinute = config('update-cdn.distribution.rate_limit', 5);
        $key = "update_cdn:ratelimit:{$ip}";

        $current = Cache::get($key, 0);
        if ($current >= $maxPerMinute) {
            return false;
        }

        Cache::increment($key, 1);
        if ($current === 0) {
            Cache::expire($key, 60);
        }
        return true;
    }

    // ── Private ──

    private function purgeSingleUrl(string $provider, string $url): void
    {
        switch ($provider) {
            case 'cloudflare':
                $this->purgeCloudflare($url);
                break;
            case 'aws':
                $this->purgeAwsCloudFront($url);
                break;
            case 'aliyun':
                $this->purgeAliyunCdn($url);
                break;
            default:
                Log::info("UpdateCdn: purge via {$provider} not implemented, skipping {$url}");
        }
    }

    private function purgeCloudflare(string $url): void
    {
        $token = config('update-cdn.purge.api_token');
        $zoneId = config('update-cdn.purge.zone_id');

        if (empty($token) || empty($zoneId)) {
            Log::warning('UpdateCdn: Cloudflare purge skipped, missing token or zone_id');
            return;
        }

        $response = Http::withToken($token)
            ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                'files' => [$url],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Cloudflare purge failed: {$response->body()}");
        }
    }

    private function purgeAwsCloudFront(string $url): void
    {
        $distributionId = config('update-cdn.purge.aws_distribution_id');
        $key = config('update-cdn.purge.aws_access_key_id');
        $secret = config('update-cdn.purge.aws_secret_access_key');

        if (empty($distributionId)) {
            Log::warning('UpdateCdn: AWS CloudFront purge skipped, missing distribution_id');
            return;
        }

        // 使用 AWS SDK 创建 invalidation（需安装 aws/aws-sdk-php）
        if (!class_exists('\Aws\CloudFront\CloudFrontClient')) {
            // 回退：直接调用 CloudFront API
            $response = Http::withBasicAuth($key ?? '', $secret ?? '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post("https://cloudfront.amazonaws.com/2020-05-31/distribution/{$distributionId}/invalidation", [
                    'InvalidationBatch' => [
                        'Paths' => [
                            'Quantity' => 1,
                            'Items' => [parse_url($url, PHP_URL_PATH)],
                        ],
                        'CallerReference' => 'hwt-' . time(),
                    ],
                ]);

            if ($response->status() === 201 || $response->status() === 200) {
                Log::info("UpdateCdn: AWS CloudFront purge initiated for {$url}");
            } else {
                Log::warning("UpdateCdn: AWS CloudFront purge responded {$response->status()}", ['body' => $response->body()]);
            }
            return;
        }

        // 使用 AWS SDK
        $client = new \Aws\CloudFront\CloudFrontClient([
            'version' => 'latest',
            'region' => config('update-cdn.purge.aws_region', 'us-east-1'),
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        $client->createInvalidation([
            'DistributionId' => $distributionId,
            'InvalidationBatch' => [
                'Paths' => [
                    'Quantity' => 1,
                    'Items' => [parse_url($url, PHP_URL_PATH)],
                ],
                'CallerReference' => 'hwt-' . time(),
            ],
        ]);

        Log::info("UpdateCdn: AWS CloudFront purge initiated for {$url}");
    }

    private function purgeAliyunCdn(string $url): void
    {
        $accessKey = config('update-cdn.purge.aliyun_access_key');
        $accessSecret = config('update-cdn.purge.aliyun_access_secret');

        if (empty($accessKey) || empty($accessSecret)) {
            Log::warning('UpdateCdn: Aliyun CDN purge skipped, missing access_key or access_secret');
            return;
        }

        // 阿里云 CDN 刷新 API (OpenAPI)
        // 参考: https://help.aliyun.com/document_detail/91164.html
        $params = [
            'Action' => 'RefreshObjectCaches',
            'ObjectPath' => $url,
            'ObjectType' => 'File',
            'Format' => 'JSON',
            'Version' => '2018-05-10',
            'AccessKeyId' => $accessKey,
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => \Illuminate\Support\Str::uuid()->toString(),
        ];

        // 排序并生成待签名字符串
        ksort($params);
        $queryStr = '';
        foreach ($params as $k => $v) {
            $queryStr .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
        }
        $stringToSign = 'POST&' . rawurlencode('/') . '&' . rawurlencode(substr($queryStr, 1));

        // 计算签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessSecret . '&', true));
        $params['Signature'] = $signature;

        $response = Http::timeout(15)
            ->post('https://cdn.aliyuncs.com', $params);

        $body = $response->json();
        if ($response->successful() && empty($body['Code'])) {
            Log::info("UpdateCdn: Aliyun CDN purge initiated for {$url}", ['request_id' => $body['RequestId'] ?? '']);
        } else {
            Log::warning("UpdateCdn: Aliyun CDN purge failed", [
                'code' => $body['Code'] ?? $response->status(),
                'message' => $body['Message'] ?? $response->body(),
            ]);
        }
    }
}
