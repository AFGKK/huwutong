<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 可嵌入式授权管理 Widget 服务 (M2-141)
 */
class EmbeddedWidgetService
{
    /**
     * 生成 Widget 嵌入令牌
     */
    public function generateToken(int $customerId, array $permissions = ['licenses:read', 'devices:read'], int $expiresIn = 3600): array
    {
        $customer = Customer::findOrFail($customerId);

        $payload = [
            'sub' => $customer->id,
            'customer_id' => $customer->id,
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + min($expiresIn, config('embedded-widget.token.max_expires_in', 86400)),
            'jti' => Str::uuid()->toString(),
        ];

        $secret = config('app.key');
        $header = $this->base64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payloadEncoded = $this->base64urlEncode(json_encode($payload));
        $signature = $this->base64urlEncode(hash_hmac('sha256', "{$header}.{$payloadEncoded}", $secret, true));

        $token = "{$header}.{$payloadEncoded}.{$signature}";

        Cache::put("widget_token:{$payload['jti']}", $payload, now()->addSeconds($expiresIn));

        $embedCode = $this->generateEmbedCode($token, $customerId);

        return [
            'token' => $token,
            'expires_in' => $payload['exp'] - time(),
            'customer_id' => $customerId,
            'permissions' => $permissions,
            'embed_code' => $embedCode,
        ];
    }

    /**
     * 验证 Widget 令牌
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        $secret = config('app.key');
        $expectedSig = $this->base64urlEncode(hash_hmac('sha256', "{$parts[0]}.{$parts[1]}", $secret, true));

        if (!hash_equals($expectedSig, $parts[2])) return null;

        $payload = json_decode($this->base64urlDecode($parts[1]), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;

        return $payload;
    }

    /**
     * 生成嵌入 HTML 代码
     */
    public function generateEmbedCode(string $token, int $customerId): string
    {
        $origin = config('app.url');
        $primaryColor = config('embedded-widget.embed.theme.primary_color', '#0f172a');

        return <<<HTML
<!-- HWT License Embedded Widget -->
<div id="hwt-widget-container" style="min-height:400px;width:100%">
  <div id="hwt-widget-loading" style="display:flex;align-items:center;justify-content:center;height:400px;color:#909399">
    加载 License 管理面板...
  </div>
</div>
<script>
(function() {
  var iframe = document.createElement('iframe');
  iframe.src = '{$origin}/widget?token={$token}&customer_id={$customerId}&theme=' + encodeURIComponent(JSON.stringify({primaryColor: '{$primaryColor}'}));
  iframe.style.width = '100%';
  iframe.style.height = '600px';
  iframe.style.border = 'none';
  iframe.style.borderRadius = '8px';
  iframe.style.overflow = 'hidden';
  iframe.onload = function() {
    document.getElementById('hwt-widget-loading').style.display = 'none';
  };
  // 响应式高度 postMessage
  window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'hwt-resize' && e.data.height) {
      iframe.style.height = e.data.height + 'px';
    }
  });
  document.getElementById('hwt-widget-container').appendChild(iframe);
})();
</script>
<!-- End HWT License Widget -->
HTML;
    }

    /**
     * 撤销令牌
     */
    public function revokeToken(string $token): bool
    {
        $payload = $this->verifyToken($token);
        if (!$payload || !isset($payload['jti'])) return false;

        Cache::forget("widget_token:{$payload['jti']}");
        Cache::put("widget_token_revoked:{$payload['jti']}", true, now()->addDays(1));
        return true;
    }

    protected function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
