<?php

namespace App\Services;

use App\Models\CorsConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * CORS 跨域策略管理服务
 *
 * 支持从数据库读取 CORS 配置，按路由模式匹配。
 * 优先级：route_pattern 最匹配 → priority 最高 → id 最小（先创建）。
 * 如果有 * 通配配置，作为兜底。
 *
 * 后台管理页面可增删改 CORS 配置。
 */
class CorsManagerService
{
    const CACHE_KEY = 'cors_configs:all';
    const CACHE_TTL = 3600;

    /**
     * 获取活跃的 CORS 配置列表（按优先级排序）
     *
     * @return CorsConfig[]
     */
    public function getActiveConfigs(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return CorsConfig::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'asc')
                ->get()
                ->all();
        });
    }

    /**
     * 为指定请求路径查找最佳匹配的 CORS 配置
     */
    public function resolveConfig(Request $request): ?CorsConfig
    {
        $path = $request->path();
        $origin = $request->header('Origin');

        if (! $origin) {
            return null;
        }

        $configs = $this->getActiveConfigs();

        foreach ($configs as $config) {
            if ($config->matchesRoute($path) && $config->matchesOrigin($origin)) {
                return $config;
            }
        }

        // 兜底：找允许 * 的配置
        foreach ($configs as $config) {
            if ($config->matchesRoute($path) && in_array('*', $config->allowed_origins ?? [])) {
                return $config;
            }
        }

        return null;
    }

    /**
     * 根据请求生成 CORS 响应头
     */
    public function buildHeaders(Request $request): array
    {
        $config = $this->resolveConfig($request);
        $origin = $request->header('Origin', '*');

        if (! $config) {
            // 默认最小化 CORS
            return [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Max-Age' => '86400',
            ];
        }

        $headers = [];

        // Access-Control-Allow-Origin
        $origins = $config->allowed_origins ?? ['*'];
        $headers['Access-Control-Allow-Origin'] = in_array('*', $origins) ? '*' : $origin;

        // Access-Control-Allow-Methods
        $headers['Access-Control-Allow-Methods'] = implode(', ', $config->allowed_methods ?? [
            'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS',
        ]);

        // Access-Control-Allow-Headers
        $headers['Access-Control-Allow-Headers'] = implode(', ', $config->allowed_headers ?? [
            'Content-Type', 'Authorization',
        ]);

        // Access-Control-Expose-Headers
        if (! empty($config->exposed_headers)) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $config->exposed_headers);
        }

        // Access-Control-Allow-Credentials
        if ($config->allow_credentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // Access-Control-Max-Age
        $headers['Access-Control-Max-Age'] = (string) ($config->max_age ?: 86400);

        return $headers;
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * 创建 CORS 配置
     */
    public function create(array $data): CorsConfig
    {
        $config = CorsConfig::create($data);
        $this->clearCache();
        return $config;
    }

    /**
     * 更新 CORS 配置
     */
    public function update(CorsConfig $config, array $data): CorsConfig
    {
        $config->update($data);
        $this->clearCache();
        return $config->fresh();
    }

    /**
     * 删除 CORS 配置
     */
    public function delete(CorsConfig $config): void
    {
        $config->delete();
        $this->clearCache();
    }
}
