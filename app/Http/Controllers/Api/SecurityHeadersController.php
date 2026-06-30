<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecurityHeadersController extends Controller
{
    const CACHE_KEY = 'security_headers_config';

    /**
     * 获取安全响应头配置
     */
    public function index(): JsonResponse
    {
        $config = $this->getConfig();
        return ApiResponse::success($config);
    }

    /**
     * 更新安全响应头配置
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hsts' => 'nullable|boolean',
            'hsts_max_age' => 'nullable|integer|min:0|max:31536000',
            'hsts_include_subdomains' => 'nullable|boolean',
            'x_frame_options' => 'nullable|in:DENY,SAMEORIGIN,ALLOW-FROM,off',
            'x_frame_options_origin' => 'nullable|string|max:500',
            'x_content_type_options' => 'nullable|in:nosniff,off',
            'referrer_policy' => 'nullable|in:no-referrer,no-referrer-when-downgrade,origin,origin-when-cross-origin,same-origin,strict-origin,strict-origin-when-cross-origin,unsafe-url,off',
            'permissions_policy' => 'nullable|string|max:1000',
            'permissions_policy_enabled' => 'nullable|boolean',
            'x_xss_protection' => 'nullable|in:1; mode=block,1,0,off',
            'cache_control' => 'nullable|string|max:200',
            'cache_control_enabled' => 'nullable|boolean',
        ]);

        $config = array_merge($this->getConfig(), $validated);
        Cache::forever(self::CACHE_KEY, $config);

        return ApiResponse::success($config, '安全响应头配置已更新');
    }

    /**
     * 重置为默认值
     */
    public function reset(): JsonResponse
    {
        Cache::forget(self::CACHE_KEY);
        return ApiResponse::success($this->getConfig(), '已恢复默认配置');
    }

    /**
     * 获取生效的响应头预览
     */
    public function preview(): JsonResponse
    {
        $config = $this->getConfig();
        $headers = [];

        if ($config['hsts'] ?? true) {
            $value = "max-age={$config['hsts_max_age']}";
            if ($config['hsts_include_subdomains'] ?? true) {
                $value .= '; includeSubDomains';
            }
            $headers['Strict-Transport-Security'] = $value;
        }
        if (($config['x_frame_options'] ?? 'DENY') !== 'off') {
            $value = $config['x_frame_options'];
            if ($value === 'ALLOW-FROM' && !empty($config['x_frame_options_origin'])) {
                $value .= " {$config['x_frame_options_origin']}";
            }
            $headers['X-Frame-Options'] = $value;
        }
        if (($config['x_content_type_options'] ?? 'nosniff') !== 'off') {
            $headers['X-Content-Type-Options'] = $config['x_content_type_options'];
        }
        if (($config['referrer_policy'] ?? 'strict-origin-when-cross-origin') !== 'off') {
            $headers['Referrer-Policy'] = $config['referrer_policy'];
        }
        if ($config['permissions_policy_enabled'] ?? true) {
            $headers['Permissions-Policy'] = $config['permissions_policy'];
        }
        if (($config['x_xss_protection'] ?? '1; mode=block') !== 'off') {
            $headers['X-XSS-Protection'] = $config['x_xss_protection'];
        }
        if ($config['cache_control_enabled'] ?? true) {
            $headers['Cache-Control'] = $config['cache_control'];
        }

        return ApiResponse::success(['headers' => $headers, 'config' => $config]);
    }

    protected function getConfig(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return [
                'hsts' => true,
                'hsts_max_age' => 31536000,
                'hsts_include_subdomains' => true,
                'x_frame_options' => 'DENY',
                'x_frame_options_origin' => '',
                'x_content_type_options' => 'nosniff',
                'referrer_policy' => 'strict-origin-when-cross-origin',
                'permissions_policy_enabled' => true,
                'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
                'x_xss_protection' => '1; mode=block',
                'cache_control_enabled' => true,
                'cache_control' => 'no-store, no-cache, must-revalidate',
            ];
        });
    }
}
