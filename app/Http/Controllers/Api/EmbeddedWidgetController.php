<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Device;
use App\Models\Customer;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 可嵌入式授权管理 Widget API
 *
 * M2-141: 客户可在自己产品后台嵌入 License 管理微前端组件
 * 支持 JWT 签名免登 + 自定义品牌色/Logo + postMessage 通信
 */
class EmbeddedWidgetController extends Controller
{
    public function __construct(
        private LicenseService $licenseService,
    ) {}

    /**
     * 生成 Widget 嵌入令牌
     *
     * POST /api/widget/token
     * Body: { customer_id, permissions, expires_in }
     */
    public function generateToken(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'in:licenses:read,licenses:write,devices:read,devices:write',
            'expires_in' => 'sometimes|integer|min:300|max:86400',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        $payload = [
            'sub' => $customer->id,
            'customer_id' => $customer->id,
            'permissions' => $request->permissions ?? ['licenses:read', 'devices:read'],
            'iat' => time(),
            'exp' => time() + ($request->expires_in ?? 3600),
            'jti' => Str::uuid()->toString(),
        ];

        // 使用 HMAC-SHA256 签名（与 Sanctum 分开的轻量令牌）
        $secret = config('app.key');
        $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payloadEncoded = base64url_encode(json_encode($payload));
        $signature = base64url_encode(hash_hmac('sha256', "{$header}.{$payloadEncoded}", $secret, true));

        $token = "{$header}.{$payloadEncoded}.{$signature}";

        // 缓存令牌元数据
        Cache::put("widget_token:{$payload['jti']}", [
            'customer_id' => $customer->id,
            'permissions' => $payload['permissions'],
        ], $payload['exp']);

        return ApiResponse::success([
            'token' => $token,
            'expires_at' => date('c', $payload['exp']),
            'embed_url' => url("/widget/embed?token={$token}"),
        ], __('app.embedded_widget.widget'));
    }

    /**
     * Widget 数据接口（JWT 认证）
     *
     * GET /api/widget/data
     * Header: Authorization: Bearer <token>
     */
    public function getWidgetData(Request $request)
    {
        $customerId = $request->get('widget_customer_id');
        $permissions = $request->get('widget_permissions', []);

        $data = ['customer' => null, 'licenses' => [], 'devices' => [], 'stats' => []];

        // 客户信息（关联 user 获取名称/邮箱）
        $customer = Customer::with('user:id,name,email,avatar')->find($customerId);
        if ($customer) {
            $data['customer'] = [
                'id' => $customer->id,
                'name' => $customer->user?->name ?? "客户 #{$customer->id}",
                'email' => $customer->user?->email ?? '',
                'company' => $customer->type === 'enterprise' ? '企业客户' : '',
                'avatar' => $customer->user?->avatar ?? '',
            ];
        }

        // License 列表
        if (in_array('licenses:read', $permissions)) {
            $data['licenses'] = License::where('customer_id', $customerId)
                ->select('id', 'license_key', 'status', 'product_id', 'expires_at', 'created_at')
                ->with('product:id,name')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->toArray();
        }

        // 设备列表（通过 license 关联到客户）
        if (in_array('devices:read', $permissions)) {
            $data['devices'] = Device::whereHas('license', fn($q) => $q->where('customer_id', $customerId))
                ->select('id', 'fingerprint', 'platform', 'is_blacklisted', 'last_seen_at', 'trust_score')
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->get()
                ->toArray();
        }

        // 统计
        $licenses = License::where('customer_id', $customerId);
        $data['stats'] = [
            'total_licenses' => (clone $licenses)->count(),
            'active_licenses' => (clone $licenses)->where('status', 'active')->count(),
            'expiring_soon' => (clone $licenses)->where('status', 'active')
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
            'expired' => (clone $licenses)->where('status', 'expired')->count(),
            'total_devices' => count($data['devices']),
        ];

        return ApiResponse::success($data);
    }

    /**
     * Widget 配置接口（品牌信息）
     *
     * GET /api/widget/config
     */
    public function getWidgetConfig(Request $request)
    {
        $customerId = $request->get('widget_customer_id');

        $config = [
            'brand_name' => 'HWT License',
            'primary_color' => '#1a73e8',
            'logo_url' => url('/images/logo.svg'),
            'locale' => app()->getLocale(),
        ];

        // 允许从请求参数覆盖
        if ($request->has('primary_color')) {
            $config['primary_color'] = $request->primary_color;
        }
        if ($request->has('brand_name')) {
            $config['brand_name'] = $request->brand_name;
        }

        return ApiResponse::success($config);
    }

    /**
     * Widget 操作 - 激活 License
     */
    public function activateLicense(Request $request)
    {
        $request->validate(['license_key' => 'required|string']);
        $permissions = $request->get('widget_permissions', []);

        if (!in_array('licenses:write', $permissions)) {
            abort(403, __("app.embedded_widget.msg_0952db27"));
        }

        try {
            $result = $this->licenseService->activate($request->license_key, [
                'device_name' => 'Widget Activation',
                'platform' => 'web-embed',
                'device_fingerprint' => 'widget-' . md5($request->ip() . $request->userAgent()),
            ]);
            return ApiResponse::success($result, __("app.embedded_widget.msg_240dec1f"));
        } catch (\Throwable $e) {
            return ApiResponse::error('ACTIVATION_FAILED', $e->getMessage(), 400);
        }
    }
}

// ─── Helper ───
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
