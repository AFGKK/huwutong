<?php

namespace App\Services;

use App\Services\Sms\AliyunSmsClient;
use Illuminate\Support\Facades\Log;

/**
 * 短信发送服务（D-04）
 *
 * 驱动: log | aliyun
 */
class SmsService
{
    public function __construct(
        private readonly ?AliyunSmsClient $aliyunClient = null,
    ) {}

    /**
     * @return array{success: bool, message: string, driver: string, request_id?: ?string}
     */
    public function sendVerificationCode(string $phone, string $code): array
    {
        return $this->sendWithDriver(
            $phone,
            config('sms.aliyun.template_code', ''),
            ['code' => $code],
            "验证码: {$code}",
            'verification',
        );
    }

    /**
     * @return array{success: bool, message: string, driver: string, request_id?: ?string}
     */
    public function sendNotification(string $phone, string $message): array
    {
        $template = config('sms.aliyun.notification_template_code', '');

        if ($template !== '') {
            return $this->sendWithDriver(
                $phone,
                $template,
                ['message' => $message],
                $message,
                'notification',
            );
        }

        return $this->sendWithDriver(
            $phone,
            config('sms.aliyun.template_code', ''),
            ['code' => mb_substr($message, 0, 20)],
            $message,
            'notification',
        );
    }

    /**
     * @return array{success: bool, message: string, driver: string, request_id?: ?string}
     */
    private function sendWithDriver(
        string $phone,
        string $templateCode,
        array $templateParams,
        string $logMessage,
        string $purpose,
    ): array {
        $driver = config('sms.driver', 'log');

        if ($driver === 'aliyun') {
            $result = $this->aliyun()->sendTemplate($phone, $templateCode, $templateParams);

            if ($result['success']) {
                Log::info('SMS: aliyun sent', [
                    'purpose' => $purpose,
                    'phone' => $this->maskPhone($phone),
                    'request_id' => $result['request_id'],
                ]);

                return [
                    'success' => true,
                    'message' => '发送成功',
                    'driver' => 'aliyun',
                    'request_id' => $result['request_id'],
                ];
            }

            Log::error('SMS: aliyun failed', [
                'purpose' => $purpose,
                'phone' => $this->maskPhone($phone),
                'code' => $result['code'],
                'message' => $result['message'],
            ]);

            if (config('sms.fallback_to_log', true) && ! app()->environment('production')) {
                return $this->logOnly($phone, $logMessage, 'aliyun_fallback');
            }

            return [
                'success' => false,
                'message' => $result['message'],
                'driver' => 'aliyun',
                'request_id' => $result['request_id'],
            ];
        }

        return $this->logOnly($phone, $logMessage, $driver);
    }

    /**
     * @return array{success: bool, message: string, driver: string}
     */
    private function logOnly(string $phone, string $message, string $driver = 'log'): array
    {
        Log::info('SMS: [log driver]', [
            'phone' => $phone,
            'message' => $message,
            'driver' => $driver,
        ]);

        return [
            'success' => true,
            'message' => '已写入日志（开发模式）',
            'driver' => $driver,
        ];
    }

    private function aliyun(): AliyunSmsClient
    {
        return $this->aliyunClient ?? new AliyunSmsClient(config('sms.aliyun', []));
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) {
            return '****';
        }

        return substr($phone, 0, 3).'****'.substr($phone, -4);
    }
}
