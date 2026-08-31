<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 阿里云短信 RPC API（SendSms）客户端
 */
class AliyunSmsClient
{
    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * @return array{success: bool, message: string, request_id: ?string, code: ?string}
     */
    public function sendTemplate(string $phone, string $templateCode, array $templateParams): array
    {
        $accessKeyId = $this->config['access_key_id'] ?? '';
        $accessKeySecret = $this->config['access_key_secret'] ?? '';
        $signName = $this->config['sign_name'] ?? '';

        if ($accessKeyId === '' || $accessKeySecret === '' || $templateCode === '') {
            return [
                'success' => false,
                'message' => '阿里云短信未配置完整',
                'request_id' => null,
                'code' => 'CONFIG_ERROR',
            ];
        }

        $params = [
            'AccessKeyId' => $accessKeyId,
            'Action' => 'SendSms',
            'Format' => 'JSON',
            'PhoneNumbers' => $phone,
            'RegionId' => $this->config['region_id'] ?? 'cn-hangzhou',
            'SignName' => $signName,
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => Str::uuid()->toString(),
            'SignatureVersion' => '1.0',
            'TemplateCode' => $templateCode,
            'TemplateParam' => json_encode($templateParams, JSON_UNESCAPED_UNICODE),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25',
        ];

        $params['Signature'] = $this->sign($params, $accessKeySecret);

        $endpoint = rtrim($this->config['endpoint'] ?? 'https://dysmsapi.aliyuncs.com', '/');

        $response = Http::timeout(15)
            ->acceptJson()
            ->get($endpoint, $params);

        $body = $response->json() ?? [];

        if (($body['Code'] ?? '') === 'OK') {
            return [
                'success' => true,
                'message' => '发送成功',
                'request_id' => $body['RequestId'] ?? null,
                'code' => 'OK',
            ];
        }

        return [
            'success' => false,
            'message' => $body['Message'] ?? '阿里云短信发送失败',
            'request_id' => $body['RequestId'] ?? null,
            'code' => $body['Code'] ?? 'UNKNOWN',
        ];
    }

    private function sign(array $params, string $accessKeySecret): string
    {
        ksort($params);

        $canonicalized = '';
        foreach ($params as $key => $value) {
            if ($key === 'Signature' || $value === null || $value === '') {
                continue;
            }
            $canonicalized .= '&'.$this->percentEncode((string) $key).'='.$this->percentEncode((string) $value);
        }

        $stringToSign = 'GET&'.$this->percentEncode('/').'&'.$this->percentEncode(substr($canonicalized, 1));

        return base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));
    }

    private function percentEncode(string $value): string
    {
        $encoded = rawurlencode($value);

        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], $encoded);
    }
}
