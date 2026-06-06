<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 请求体大小分级限制中间件
 *
 * 按路径/API 类型限制请求体大小：
 *   - 激活/验证 API :   10 KB
 *   - 上传类 API     :   10 MB
 *   - 全局默认       :    1 MB
 *
 * 超限请求返回 413 Payload Too Large + 统一错误格式
 *
 * 使用方式：
 *   Route::middleware('body-limit')->post('/api/license/activate', ...);
 *   Route::middleware('body-limit:10240')->post('/api/upload', ...); // 自定义限制
 */
class BodySizeLimiter
{
    /**
     * 各路由组的默认限制（字节）
     */
    const LIMITS = [
        'default' => 1 * 1024 * 1024,       // 1 MB
        'activate' => 10 * 1024,             // 10 KB
        'upload' => 10 * 1024 * 1024,        // 10 MB
    ];

    public function handle(Request $request, Closure $next, string $limitKey = 'default'): Response
    {
        // 仅限制 POST/PUT/PATCH/DELETE 请求
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // 解析限制大小
        $maxBytes = $this->resolveLimit($limitKey);

        // 获取 Content-Length
        $contentLength = $request->header('Content-Length');

        if ($contentLength !== null && (int) $contentLength > $maxBytes) {
            return ApiResponse::error(
                'PAYLOAD_TOO_LARGE',
                "请求体过大，最大允许 {$this->formatBytes($maxBytes)}",
                413,
            );
        }

        // 检查实际 body 大小（处理分块传输）
        $body = $request->getContent();
        if (strlen($body) > $maxBytes) {
            return ApiResponse::error(
                'PAYLOAD_TOO_LARGE',
                "请求体过大，最大允许 {$this->formatBytes($maxBytes)}",
                413,
            );
        }

        return $next($request);
    }

    /**
     * 解析限制大小
     */
    protected function resolveLimit(string $key): int
    {
        // 数字字符串 → 视为字节数
        if (is_numeric($key)) {
            return (int) $key;
        }

        return self::LIMITS[$key] ?? self::LIMITS['default'];
    }

    /**
     * 格式化字节数为人类可读
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
