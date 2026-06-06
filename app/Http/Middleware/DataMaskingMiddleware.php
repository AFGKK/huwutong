<?php

namespace App\Http\Middleware;

use App\Services\DataMaskingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 敏感数据脱敏中间件
 *
 * API JSON 响应自动对邮箱/手机号/IP/姓名等敏感字段进行脱敏处理。
 * 脱敏程度按角色分级：
 *   - 超管 (super_admin/admin) → 全量显示
 *   - 运营 (operator/support) → 部分脱敏
 *   - 客户 (customer/默认) → 最小化脱敏
 *
 * 脱敏在响应发送前进行，不修改原始数据。
 */
class DataMaskingMiddleware
{
    protected DataMaskingService $maskingService;

    public function __construct(DataMaskingService $maskingService)
    {
        $this->maskingService = $maskingService;
    }

    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 仅处理 JSON 响应
        if (! $response instanceof Response || ! str_contains($response->headers->get('Content-Type', ''), 'json')) {
            return $response;
        }

        // 获取原始内容
        $content = $response->getContent();
        if (empty($content)) {
            return $response;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $response;
        }

        // 确定角色级别
        $role = $this->resolveRole($request);

        // 对响应 body 中的敏感字段进行脱敏
        $masked = $this->maskResponseData($data, $role);

        $response->setContent(json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    /**
     * 确定当前请求的角色级别
     */
    protected function resolveRole(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return 'customer';
        }

        // 超管
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return 'admin';
        }

        // 运营/客服
        if ($user->hasRole('operator') || $user->hasRole('support') || $user->hasRole('agent')) {
            return 'operator';
        }

        return 'customer';
    }

    /**
     * 递归脱敏响应数据
     */
    protected function maskResponseData(array $data, string $role): array
    {
        // 对 data 和 error.details 字段进行脱敏
        if (array_key_exists('data', $data)) {
            $data['data'] = $this->maskNestedData($data['data'], $role);
        }

        if (isset($data['error']['details']) && is_array($data['error']['details'])) {
            $data['error']['details'] = $this->maskingService->maskArray($data['error']['details'], $role);
        }

        // 对 meta 中的邮箱/手机等也脱敏
        if (isset($data['meta']) && is_array($data['meta']['debug'] ?? null)) {
            $data['meta']['debug'] = $this->maskingService->maskArray($data['meta']['debug'], $role);
        }

        return $data;
    }

    /**
     * 递归处理嵌套数据
     * 支持对象列表和嵌套对象
     */
    protected function maskNestedData(mixed $data, string $role): mixed
    {
        if (is_array($data)) {
            // 判断是否为列表（索引数组）
            if (array_keys($data) === range(0, count($data) - 1)) {
                // 对象列表 — 对列表中每一项脱敏
                return array_map(fn ($item) => is_array($item)
                    ? $this->maskingService->maskArray($item, $role)
                    : $item, $data);
            }

            // 关联数组 — 直接脱敏
            return $this->maskingService->maskArray($data, $role);
        }

        return $data;
    }
}
