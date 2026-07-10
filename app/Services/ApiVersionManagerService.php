<?php

namespace App\Services;

use App\Models\ApiVersion;
use App\Models\ApiVersionCall;
use App\Models\ApiVersionRoute;
use App\Support\DbSql;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * API 版本管理策略
 *
 * 职责：
 *  - /v1 /v2 版本共存
 *  - 废弃（deprecated）标记 + 6 个月废弃时间线
 *  - 版本路由注册和发现
 *  - 版本调用统计和影响分析
 *  - Sunset/Retirement 自动过期
 */
class ApiVersionManagerService
{
    /**
     * 废弃后默认保留月数
     */
    const DEPRECATION_GRACE_MONTHS = 6;

    /**
     * 版本缓存 TTL（秒）
     */
    const VERSION_CACHE_TTL = 3600;

    /**
     * 获取所有版本
     */
    public function getAllVersions(): array
    {
        $versions = Cache::remember('api_versions:all', self::VERSION_CACHE_TTL, function () {
            return ApiVersion::orderBy('created_at')->get()->toArray();
        });

        return $versions;
    }

    /**
     * 根据版本号获取版本信息
     */
    public function getVersion(string $version): ?ApiVersion
    {
        return Cache::remember(
            "api_version:{$version}",
            self::VERSION_CACHE_TTL,
            fn() => ApiVersion::where('version', $version)->first()
        );
    }

    /**
     * 获取默认版本
     */
    public function getDefaultVersion(): ?ApiVersion
    {
        return Cache::remember('api_version:default', self::VERSION_CACHE_TTL, function () {
            return ApiVersion::where('is_default', true)->first()
                ?? ApiVersion::where('status', ApiVersion::STATUS_ACTIVE)->first();
        });
    }

    /**
     * 获取当前活跃版本列表
     */
    public function getActiveVersions(): array
    {
        return Cache::remember('api_versions:active', self::VERSION_CACHE_TTL, function () {
            return ApiVersion::active()->orderBy('created_at')->get()->toArray();
        });
    }

    /**
     * 创建新版本
     */
    public function createVersion(array $data): ApiVersion
    {
        if (empty($data['version'])) {
            throw new \InvalidArgumentException('版本号不能为空');
        }

        $version = str_starts_with($data['version'], 'v') ? $data['version'] : 'v' . $data['version'];
        $basePath = $data['base_path'] ?? "/api/{$version}";

        $apiVersion = ApiVersion::create([
            'version' => $version,
            'base_path' => $basePath,
            'name' => $data['name'] ?? null,
            'status' => $data['status'] ?? ApiVersion::STATUS_ACTIVE,
            'deprecated_at' => $data['deprecated_at'] ?? null,
            'sunset_at' => $data['sunset_at'] ?? null,
            'changelog' => $data['changelog'] ?? null,
            'migration_guide' => $data['migration_guide'] ?? null,
            'deprecation_notice' => $data['deprecation_notice'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);

        // 如果设为默认，取消其他默认
        if ($apiVersion->is_default) {
            ApiVersion::where('id', '!=', $apiVersion->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $this->clearVersionCache();

        return $apiVersion;
    }

    /**
     * 更新版本
     */
    public function updateVersion(ApiVersion $apiVersion, array $data): ApiVersion
    {
        $apiVersion->update($data);

        if (!empty($data['is_default']) && $data['is_default']) {
            ApiVersion::where('id', '!=', $apiVersion->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $this->clearVersionCache();

        return $apiVersion->fresh();
    }

    /**
     * 标记版本为废弃
     */
    public function deprecateVersion(ApiVersion $apiVersion, ?string $migrationGuide = null, ?string $notice = null): ApiVersion
    {
        $apiVersion->update([
            'status' => ApiVersion::STATUS_DEPRECATED,
            'deprecated_at' => now(),
            'sunset_at' => now()->addMonths(self::DEPRECATION_GRACE_MONTHS),
            'migration_guide' => $migrationGuide ?? $apiVersion->migration_guide,
            'deprecation_notice' => $notice ?? $apiVersion->deprecation_notice,
        ]);

        // 将改版本下的所有路由标记为废弃
        ApiVersionRoute::where('api_version_id', $apiVersion->id)
            ->update(['is_deprecated' => true]);

        $this->clearVersionCache();

        return $apiVersion->fresh();
    }

    /**
     * 停用版本（sunset）
     */
    public function sunsetVersion(ApiVersion $apiVersion): ApiVersion
    {
        $apiVersion->update([
            'status' => ApiVersion::STATUS_SUNSET,
            'sunset_at' => now(),
        ]);

        $this->clearVersionCache();

        return $apiVersion->fresh();
    }

    /**
     * 退役版本（retire）
     */
    public function retireVersion(ApiVersion $apiVersion): ApiVersion
    {
        $apiVersion->update([
            'status' => ApiVersion::STATUS_RETIRED,
            'retired_at' => now(),
        ]);

        $this->clearVersionCache();

        return $apiVersion->fresh();
    }

    /**
     * 删除版本
     */
    public function deleteVersion(ApiVersion $apiVersion): bool
    {
        $apiVersion->routes()->delete();
        $apiVersion->calls()->delete();

        $result = $apiVersion->delete();

        $this->clearVersionCache();

        return $result;
    }

    /**
     * 注册版本路由
     */
    public function registerRoute(ApiVersion $apiVersion, string $method, string $path, ?string $routeName = null, ?string $controller = null, ?string $action = null): ApiVersionRoute
    {
        return ApiVersionRoute::create([
            'api_version_id' => $apiVersion->id,
            'method' => strtoupper($method),
            'path' => $path,
            'route_name' => $routeName,
            'controller' => $controller,
            'action' => $action,
            'is_deprecated' => $apiVersion->isDeprecated(),
        ]);
    }

    /**
     * 批量导入路由
     */
    public function importRoutes(ApiVersion $apiVersion, array $routes): int
    {
        $count = 0;
        foreach ($routes as $route) {
            $this->registerRoute(
                $apiVersion,
                $route['method'],
                $route['path'],
                $route['route_name'] ?? null,
                $route['controller'] ?? null,
                $route['action'] ?? null,
            );
            $count++;
        }

        return $count;
    }

    /**
     * 记录 API 调用
     */
    public function recordCall(ApiVersion $apiVersion, Request $request, ?int $tenantId = null): void
    {
        $today = now()->toDateString();
        $method = $request->method();
        $path = $request->path();

        try {
            $now = DbSql::now();
            if (DbSql::driver() === 'pgsql') {
                DB::statement(
                    "INSERT INTO api_version_calls (api_version_id, tenant_id, method, path, call_count, call_date, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, ?, {$now}, {$now})
                     ON CONFLICT (api_version_id, tenant_id, method, path, call_date)
                     DO UPDATE SET call_count = api_version_calls.call_count + 1, updated_at = {$now}",
                    [$apiVersion->id, $tenantId, $method, $path, $today]
                );
            } else {
                DB::statement(
                    "INSERT INTO api_version_calls (api_version_id, tenant_id, method, path, call_count, call_date, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, ?, {$now}, {$now})
                     ON DUPLICATE KEY UPDATE call_count = call_count + 1, updated_at = {$now}",
                    [$apiVersion->id, $tenantId, $method, $path, $today]
                );
            }
        } catch (\Exception $e) {
            // Log silently, don't break the request
            logger()->warning('Failed to record API version call', [
                'version' => $apiVersion->version,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 从请求路径中提取版本号
     */
    public function extractVersionFromPath(string $path): ?string
    {
        if (preg_match('#^api/v(\d+(\.\d+)?)/#', $path, $matches)) {
            return 'v' . $matches[1];
        }

        return null;
    }

    /**
     * 检查请求的版本是否可用，返回版本信息和响应头
     */
    public function checkRequestVersion(string $path): array
    {
        $versionStr = $this->extractVersionFromPath($path);
        $version = $versionStr ? $this->getVersion($versionStr) : $this->getDefaultVersion();

        if (!$version) {
            return [
                'available' => false,
                'status_code' => 404,
                'message' => "API version '{$versionStr}' not found",
                'headers' => [],
            ];
        }

        if ($version->isRetired()) {
            return [
                'available' => false,
                'status_code' => 410,
                'message' => "API version '{$version->version}' has been retired since {$version->retired_at->format('Y-m-d')}",
                'headers' => [
                    'X-API-Version-Status' => 'retired',
                    'X-API-Retired-At' => $version->retired_at->toRfc7231String(),
                ],
            ];
        }

        $headers = [
            'X-API-Version' => $version->version,
            'X-API-Version-Status' => $version->status,
        ];

        if ($version->isDeprecated()) {
            $headers['X-API-Version-Status'] = 'deprecated';
            $headers['X-API-Deprecated-At'] = $version->deprecated_at->toRfc7231String();
            $headers['X-API-Sunset-At'] = $version->sunset_at->toRfc7231String();
            $headers['X-API-Deprecation-Notice'] = $version->deprecation_notice
                ?? "This API version ({$version->version}) is deprecated. "
                   . "Please migrate to the latest version. "
                   . "Support ends {$version->sunset_at->format('Y-m-d')}.";

            if ($version->migration_guide) {
                $headers['X-API-Migration-Guide'] = $version->migration_guide;
            }
        }

        return [
            'available' => true,
            'version' => $version,
            'headers' => $headers,
        ];
    }

    /**
     * 获取版本调用统计（按日）
     */
    public function getVersionCallStats(int $apiVersionId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = ApiVersionCall::where('api_version_id', $apiVersionId);

        if ($startDate) {
            $query->where('call_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('call_date', '<=', $endDate);
        }

        return $query->orderBy('call_date', 'desc')
            ->select('method', 'path', 'call_date', DB::raw('SUM(call_count) as total_calls'))
            ->groupBy('method', 'path', 'call_date')
            ->get()
            ->toArray();
    }

    /**
     * 获取版本使用趋势
     */
    public function getVersionUsageTrend(?string $startDate = null, ?string $endDate = null): array
    {
        $query = ApiVersionCall::select(
            'api_version_id',
            'call_date',
            DB::raw('SUM(call_count) as total_calls')
        )->groupBy('api_version_id', 'call_date');

        if ($startDate) {
            $query->where('call_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('call_date', '<=', $endDate);
        }

        $records = $query->orderBy('call_date')->get();

        $trend = [];
        foreach ($records as $record) {
            $apiVersion = ApiVersion::find($record->api_version_id);
            if (!$apiVersion) continue;

            $trend[] = [
                'version' => $apiVersion->version,
                'date' => $record->call_date,
                'calls' => (int) $record->total_calls,
                'status' => $apiVersion->status,
            ];
        }

        return $trend;
    }

    /**
     * 获取影响分析：哪些客户在使用旧版本
     */
    public function getImpactAnalysis(int $apiVersionId): array
    {
        $calls = ApiVersionCall::where('api_version_id', $apiVersionId)
            ->whereNotNull('tenant_id')
            ->select(
                'tenant_id',
                DB::raw('SUM(call_count) as total_calls'),
                DB::raw('MAX(call_date) as last_call_date')
            )
            ->groupBy('tenant_id')
            ->orderByDesc('total_calls')
            ->get();

        $result = [];
        foreach ($calls as $call) {
            $tenant = $call->tenant;
            $result[] = [
                'tenant_id' => $call->tenant_id,
                'tenant_name' => $tenant?->name ?? 'Deleted Tenant',
                'total_calls' => (int) $call->total_calls,
                'last_call_date' => $call->last_call_date,
            ];
        }

        return $result;
    }

    /**
     * 处理过期的废弃版本（超 6 个月自动 sunset）
     */
    public function processExpiredDeprecations(): array
    {
        $processed = [];

        $expired = ApiVersion::where('status', ApiVersion::STATUS_DEPRECATED)
            ->where('sunset_at', '<=', now())
            ->get();

        foreach ($expired as $version) {
            $this->sunsetVersion($version);
            $processed[] = $version->version;
        }

        // Sunset 超过 30 天的自动退休
        $toRetire = ApiVersion::where('status', ApiVersion::STATUS_SUNSET)
            ->where('updated_at', '<=', now()->subDays(30))
            ->get();

        foreach ($toRetire as $version) {
            $this->retireVersion($version);
            $processed[] = $version->version . ' (retired)';
        }

        return $processed;
    }

    /**
     * 清理版本缓存
     */
    public function clearVersionCache(): void
    {
        Cache::forget('api_versions:all');
        Cache::forget('api_versions:active');
        Cache::forget('api_version:default');

        // 清除所有版本单独缓存
        $versions = ApiVersion::all();
        foreach ($versions as $version) {
            Cache::forget("api_version:{$version->version}");
        }
    }
}
