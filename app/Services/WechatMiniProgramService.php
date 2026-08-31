<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 微信小程序服务：access_token、订阅消息
 */
class WechatMiniProgramService
{
    public function getConfig(): array
    {
        $cfg = SiteSetting::getWechatMiniProgramConfig();

        return [
            'appid' => $cfg['appid'] ?? '',
            'secret' => $cfg['secret'] ?? '',
            'subscribe_template_id' => SiteSetting::where('key', 'wechat_mini_subscribe_template_id')->value('value') ?? '',
        ];
    }

    public function getAccessToken(): ?string
    {
        $cfg = $this->getConfig();
        if ($cfg['appid'] === '' || $cfg['secret'] === '') {
            return null;
        }

        $cacheKey = 'wechat_mp_access_token_' . $cfg['appid'];

        return Cache::remember($cacheKey, 7000, function () use ($cfg) {
            $res = Http::timeout(10)->get('https://api.weixin.qq.com/cgi-bin/token', [
                'grant_type' => 'client_credential',
                'appid' => $cfg['appid'],
                'secret' => $cfg['secret'],
            ]);

            $json = $res->json() ?? [];
            if (empty($json['access_token'])) {
                Log::warning('微信小程序 access_token 获取失败', [
                    'errcode' => $json['errcode'] ?? null,
                    'errmsg' => $json['errmsg'] ?? null,
                ]);

                return null;
            }

            return $json['access_token'];
        });
    }

    /**
     * 发送订阅消息
     *
     * @param  array<string, array{value: string}>  $data
     */
    public function sendSubscribeMessage(string $openid, string $templateId, array $data, string $page = 'pages/index/index'): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'access_token unavailable'];
        }

        $res = Http::timeout(10)->post(
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $token,
            [
                'touser' => $openid,
                'template_id' => $templateId,
                'page' => $page,
                'data' => $data,
                'miniprogram_state' => 'formal',
            ]
        );

        $json = $res->json() ?? [];
        $ok = ($json['errcode'] ?? 1) === 0;

        if (! $ok) {
            Log::warning('微信订阅消息发送失败', [
                'openid' => substr($openid, 0, 8) . '…',
                'errcode' => $json['errcode'] ?? null,
                'errmsg' => $json['errmsg'] ?? null,
            ]);
            // token 失效时清缓存
            if (in_array((int) ($json['errcode'] ?? 0), [40001, 42001], true)) {
                $cfg = $this->getConfig();
                Cache::forget('wechat_mp_access_token_' . $cfg['appid']);
            }
        }

        return [
            'success' => $ok,
            'message' => $json['errmsg'] ?? ($ok ? 'ok' : 'send failed'),
            'errcode' => $json['errcode'] ?? null,
        ];
    }

    /**
     * 用 getPhoneNumber 动态 code 换取手机号
     *
     * @return array{success: bool, phone?: string, pure_phone?: string, message?: string}
     */
    public function getPhoneNumber(string $code): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'access_token unavailable'];
        }

        $res = Http::timeout(10)->post(
            'https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=' . $token,
            ['code' => $code]
        );

        $json = $res->json() ?? [];
        if (($json['errcode'] ?? 1) !== 0) {
            Log::warning('微信获取手机号失败', [
                'errcode' => $json['errcode'] ?? null,
                'errmsg' => $json['errmsg'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => $json['errmsg'] ?? 'get phone failed',
                'errcode' => $json['errcode'] ?? null,
            ];
        }

        $info = $json['phone_info'] ?? [];

        return [
            'success' => true,
            'phone' => $info['phoneNumber'] ?? '',
            'pure_phone' => $info['purePhoneNumber'] ?? ($info['phoneNumber'] ?? ''),
            'country_code' => $info['countryCode'] ?? '',
        ];
    }
}
