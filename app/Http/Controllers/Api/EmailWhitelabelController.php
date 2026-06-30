<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmailWhitelabelController extends Controller
{
    const CACHE_KEY_PREFIX = 'email_whitelabel_';

    /**
     * 获取邮件白标配置
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $config = $this->getConfig($tenantId);
        return ApiResponse::success($config);
    }

    /**
     * 更新邮件白标配置
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'from_name' => 'nullable|string|max:100',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'return_path' => 'nullable|email|max:255',
            'dkim_enabled' => 'nullable|boolean',
            'dkim_selector' => 'nullable|string|max:50',
            'dkim_private_key' => 'nullable|string',
            'dkim_public_key' => 'nullable|string',
            'spf_enabled' => 'nullable|boolean',
            'spf_record' => 'nullable|string|max:500',
            'dmarc_enabled' => 'nullable|boolean',
            'dmarc_record' => 'nullable|string|max:500',
            'dmarc_policy' => 'nullable|in:none,quarantine,reject',
            'is_active' => 'nullable|boolean',
        ]);

        // 验证 DKIM 私钥格式
        if (!empty($validated['dkim_private_key'])) {
            if (!str_contains($validated['dkim_private_key'], '-----BEGIN')) {
                return ApiResponse::error('DKIM 私钥格式无效，需要 PEM 格式');
            }
        }

        // 验证 SPF 记录格式
        if (!empty($validated['spf_record']) && !str_starts_with($validated['spf_record'], 'v=spf1')) {
            return ApiResponse::error('SPF 记录必须以 v=spf1 开头');
        }

        // 验证 DMARC 记录格式
        if (!empty($validated['dmarc_record']) && !str_starts_with($validated['dmarc_record'], 'v=DMARC1')) {
            return ApiResponse::error('DMARC 记录必须以 v=DMARC1 开头');
        }

        $config = array_merge($this->getConfig($tenantId), $validated);
        $config['updated_at'] = now()->toIso8601String();

        Cache::forever(self::CACHE_KEY_PREFIX . $tenantId, $config);

        return ApiResponse::success($config, '邮件白标配置已更新');
    }

    /**
     * 获取 DNS 记录配置引导
     */
    public function dnsGuide(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $config = $this->getConfig($tenantId);
        $domain = $request->user()->tenant?->domain ?? parse_url(config('app.url'), PHP_URL_HOST);

        $records = [];

        // DKIM
        if ($config['dkim_enabled'] ?? false) {
            $selector = $config['dkim_selector'] ?? 'default';
            $publicKey = $config['dkim_public_key'] ?? '';
            if ($publicKey) {
                // 格式化 DKIM 公钥为 DNS 记录格式
                $keyFormatted = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $publicKey);
                $records[] = [
                    'type' => 'TXT',
                    'name' => "{$selector}._domainkey.{$domain}",
                    'value' => "v=DKIM1; h=sha256; k=rsa; p={$keyFormatted}",
                    'status' => 'pending',
                    'purpose' => 'DKIM 签名验证',
                    'guide' => "请在 DNS 管理后台添加以下 TXT 记录：\n主机记录：{$selector}._domainkey\n记录值：v=DKIM1; h=sha256; k=rsa; p={$keyFormatted}",
                ];
            }
        }

        // SPF
        if ($config['spf_enabled'] ?? false) {
            $spf = $config['spf_record'] ?? 'v=spf1 include:_spf.huwutong.com ~all';
            $records[] = [
                'type' => 'TXT',
                'name' => $domain,
                'value' => $spf,
                'status' => 'pending',
                'purpose' => 'SPF 发信认证',
                'guide' => "请在 DNS 管理后台添加以下 TXT 记录：\n主机记录：@\n记录值：{$spf}",
            ];
        }

        // DMARC
        if ($config['dmarc_enabled'] ?? false) {
            $policy = $config['dmarc_policy'] ?? 'none';
            $dmarc = $config['dmarc_record'] ?? "v=DMARC1; p={$policy}; rua=mailto:dmarc-reports@{$domain}";
            $records[] = [
                'type' => 'TXT',
                'name' => "_dmarc.{$domain}",
                'value' => $dmarc,
                'status' => 'pending',
                'purpose' => 'DMARC 防伪造',
                'guide' => "请在 DNS 管理后台添加以下 TXT 记录：\n主机记录：_dmarc\n记录值：{$dmarc}",
            ];
        }

        return ApiResponse::success([
            'domain' => $domain,
            'records' => $records,
            'from_name' => $config['from_name'],
            'from_email' => $config['from_email'],
        ]);
    }

    /**
     * 验证 DNS 配置
     */
    public function verify(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $config = $this->getConfig($tenantId);
        $results = [];

        // 模拟 DNS 查询验证（实际生产应使用 dns_get_record）
        $domain = $request->user()->tenant?->domain ?? parse_url(config('app.url'), PHP_URL_HOST);

        if ($config['dkim_enabled'] ?? false) {
            $selector = $config['dkim_selector'] ?? 'default';
            $results['dkim'] = [
                'status' => 'pending',
                'message' => "请添加 DKIM 记录后运行验证。DNS 记录：{$selector}._domainkey.{$domain}",
            ];
        }
        if ($config['spf_enabled'] ?? false) {
            $results['spf'] = [
                'status' => 'pending',
                'message' => "SPF 记录已配置。下一步：等待 DNS 生效（通常 5-30 分钟）",
            ];
        }
        if ($config['dmarc_enabled'] ?? false) {
            $results['dmarc'] = [
                'status' => 'pending',
                'message' => "DMARC 策略：{$config['dmarc_policy']}。建议从 p=none 开始逐步收紧。",
            ];
        }

        return ApiResponse::success([
            'domain' => $domain,
            'results' => $results,
            'overall_status' => count($results) > 0 ? '配置待生效' : '未配置',
        ]);
    }

    protected function getConfig(?int $tenantId): array
    {
        if (!$tenantId) {
            return $this->defaults();
        }
        return Cache::get(self::CACHE_KEY_PREFIX . $tenantId, $this->defaults());
    }

    protected function defaults(): array
    {
        return [
            'from_name' => '',
            'from_email' => '',
            'reply_to' => '',
            'return_path' => '',
            'dkim_enabled' => false,
            'dkim_selector' => 'default',
            'dkim_private_key' => '',
            'dkim_public_key' => '',
            'spf_enabled' => false,
            'spf_record' => 'v=spf1 include:_spf.huwutong.com ~all',
            'dmarc_enabled' => false,
            'dmarc_record' => '',
            'dmarc_policy' => 'none',
            'is_active' => true,
            'updated_at' => null,
        ];
    }
}
