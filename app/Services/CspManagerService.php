<?php

namespace App\Services;

use App\Models\CspConfig;
use App\Models\CspViolation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * CSP 内容安全策略管理服务
 *
 * 从数据库读取 CSP 配置，按路由模式匹配。
 * 支持 enforce（强制执行）和 report-only（仅报告）两种模式。
 * 支持收集 CSP 违规报告。
 */
class CspManagerService
{
    const CACHE_KEY = 'csp_configs:all';
    const CACHE_TTL = 3600;

    /**
     * 获取活跃的 CSP 配置列表
     *
     * @return CspConfig[]
     */
    public function getActiveConfigs(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return CspConfig::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'asc')
                ->get()
                ->all();
        });
    }

    /**
     * 为指定请求路径查找最佳的 CSP 配置
     */
    public function resolveConfig(Request $request): ?CspConfig
    {
        $path = $request->path();
        $configs = $this->getActiveConfigs();

        // 优先匹配 route_pattern
        $matched = [];
        foreach ($configs as $config) {
            if ($config->matchesRoute($path)) {
                $matched[] = $config;
            }
        }

        if (! empty($matched)) {
            return $matched[0];
        }

        return null;
    }

    /**
     * 为指定请求生成 CSP 头（含默认兜底）
     */
    public function buildHeaders(Request $request): array
    {
        $config = $this->resolveConfig($request);
        $headers = [];

        if ($config) {
            $policy = $config->toPolicyString();
            if ($config->mode === 'report-only') {
                $headers['Content-Security-Policy-Report-Only'] = $policy;
            } else {
                $headers['Content-Security-Policy'] = $policy;
            }
        } else {
            // 默认 CSP
            $defaultCsp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' *; frame-ancestors 'none'; base-uri 'self'";
            $headers['Content-Security-Policy'] = $defaultCsp;
        }

        return $headers;
    }

    /**
     * 记录 CSP 违规报告（CSP report-to endpoint）
     */
    public function reportViolation(Request $request): CspViolation
    {
        $data = $request->input('csp-report') ?? $request->all();

        $violation = CspViolation::create([
            'document_uri' => $data['document-uri'] ?? null,
            'blocked_uri' => $data['blocked-uri'] ?? null,
            'violated_directive' => $data['violated-directive'] ?? null,
            'effective_directive' => $data['effective-directive'] ?? null,
            'source_file' => $data['source-file'] ?? null,
            'line_number' => $data['line-number'] ?? null,
            'column_number' => $data['column-number'] ?? null,
            'status_code' => $data['status-code'] ?? null,
            'original_policy' => $data['original-policy'] ?? null,
            'disposition' => $data['disposition'] ?? 'report',
            'user_agent' => $request->userAgent(),
            'reported_from' => $request->ip(),
        ]);

        // 可触发通知或告警（高频违规）
        $this->checkViolationThreshold($request);

        return $violation;
    }

    /**
     * 检查违规频率，可触发告警
     */
    protected function checkViolationThreshold(Request $request): void
    {
        $recent = CspViolation::where('created_at', '>=', now()->subMinutes(5))->count();
        if ($recent > 100) {
            \Log::warning("CSP 违规频率异常: 过去5分钟 {$recent} 次违规");
        }
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * 创建 CSP 配置
     */
    public function create(array $data): CspConfig
    {
        $config = CspConfig::create($data);
        $this->clearCache();
        return $config;
    }

    /**
     * 更新 CSP 配置
     */
    public function update(CspConfig $config, array $data): CspConfig
    {
        $config->update($data);
        $this->clearCache();
        return $config->fresh();
    }

    /**
     * 删除 CSP 配置
     */
    public function delete(CspConfig $config): void
    {
        $config->delete();
        $this->clearCache();
    }
}
