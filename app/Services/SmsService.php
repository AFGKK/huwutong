<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 短信发送服务
 *
 * 支持阿里云短信 SDK 发送验证码。
 * 开发环境下仅记录日志，不实际发送。
 */
class SmsService
{
    /**
     * 发送手机验证码
     *
     * @param string $phone 手机号
     * @param string $code 验证码
     * @return bool
     */
    public function sendVerificationCode(string $phone, string $code): bool
    {
        $driver = env('SMS_DRIVER', 'log');

        return match ($driver) {
            'aliyun' => $this->sendViaAliyun($phone, $code),
            default  => $this->logOnly($phone, $code),
        };
    }

    /**
     * 通过阿里云短信发送
     */
    private function sendViaAliyun(string $phone, string $code): bool
    {
        $accessKeyId = env('ALIYUN_SMS_ACCESS_KEY_ID');
        $accessSecret = env('ALIYUN_SMS_ACCESS_KEY_SECRET');
        $signName = env('ALIYUN_SMS_SIGN_NAME', '互物通');
        $templateCode = env('ALIYUN_SMS_TEMPLATE_CODE', 'SMS_XXXXXXXX');

        if (empty($accessKeyId) || empty($accessSecret)) {
            Log::warning('SMS: 阿里云短信未配置，使用日志模式回退');
            return $this->logOnly($phone, $code);
        }

        try {
            // 阿里云短信 API 调用
            $params = [
                'PhoneNumbers' => $phone,
                'SignName' => $signName,
                'TemplateCode' => $templateCode,
                'TemplateParam' => json_encode(['code' => $code]),
            ];

            // 使用阿里云短信 SDK（需要安装 alibabacloud/sdk）
            // 以下为通用 HTTP 签名调用方式
            $timestamp = gmdate('Y-m-d\TH:i:s\Z');
            $nonce = uniqid('', true);

            $response = Http::withHeaders([
                'Authorization' => $this->signRequest($accessKeyId, $accessSecret, $params, $timestamp, $nonce),
                'X-Acs-Date' => $timestamp,
                'X-Acs-Signature-Nonce' => $nonce,
                'Content-Type' => 'application/json',
            ])->post('https://dysmsapi.aliyuncs.com/', $params);

            $result = $response->json();

            if (($result['Code'] ?? '') === 'OK') {
                Log::info('SMS: 验证码已通过阿里云发送', [
                    'phone' => substr($phone, 0, 3) . '****' . substr($phone, -4),
                    'request_id' => $result['RequestId'] ?? null,
                ]);
                return true;
            }

            Log::error('SMS: 阿里云发送失败', [
                'code' => $result['Code'] ?? 'unknown',
                'message' => $result['Message'] ?? 'no message',
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('SMS: 阿里云发送异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->logOnly($phone, $code);
        }
    }

    /**
     * 开发环境仅记录日志
     */
    private function logOnly(string $phone, string $code): bool
    {
        Log::info('SMS: [开发环境] 手机验证码', [
            'phone' => $phone,
            'code' => $code,
        ]);
        return true;
    }

    /**
     * 阿里云签名
     */
    private function signRequest(string $accessKeyId, string $accessSecret, array $params, string $timestamp, string $nonce): string
    {
        // 简化签名实现 —— 实际项目中建议使用 alibabacloud/sdk composer 包
        ksort($params);
        $canonicalizedQueryString = '';
        foreach ($params as $key => $value) {
            $canonicalizedQueryString .= '&' . rawurlencode($key) . '=' . rawurlencode($value);
        }
        $stringToSign = "POST&%2F&" . rawurlencode(substr($canonicalizedQueryString, 1));
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessSecret . '&', true));

        return "acs $accessKeyId:$signature";
    }
}
