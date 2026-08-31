<?php

namespace App\Services;

use App\Mail\VerifyCodeMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 验证码服务统一封装 (M2-66 / D-04)
 */
class VerifyCodeService
{
    const CACHE_PREFIX = 'verify_code:';

    /**
     * @return array{success: bool, message: string, expires_in: int}
     */
    public function sendEmail(string $email, string $action = 'verify', ?int $length = 6): array
    {
        $rateKey = self::CACHE_PREFIX."rate:email:{$email}:{$action}";
        if (Cache::get($rateKey)) {
            $ttl = $this->cacheTtl($rateKey, 60);

            return ['success' => false, 'message' => __('app.api.service_verify_code.rate_limit', ['ttl' => $ttl]), 'expires_in' => max($ttl, 0)];
        }

        $code = $this->generateCode($length);
        $expiresMinutes = 10;
        $ttl = $expiresMinutes * 60;

        try {
            Mail::to($email)->send(new VerifyCodeMail($code, $action, $expiresMinutes));

            Cache::put($this->emailCodeKey($email, $action), $code, $ttl);
            Cache::put($rateKey, true, config('sms.verification.rate_limit_seconds', 60));

            return ['success' => true, 'message' => __('app.api.service_verify_code.code_sent'), 'expires_in' => $ttl];
        } catch (\Throwable $e) {
            Log::error("VerifyCode: email send failed to {$email}", ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => __('app.api.service_verify_code.send_failed'), 'expires_in' => 0];
        }
    }

    /**
     * @return array{success: bool, message: string, expires_in: int}
     */
    public function sendSms(string $phone, string $action = 'login', ?int $length = 6): array
    {
        $rateKey = self::CACHE_PREFIX."rate:sms:{$phone}:{$action}";
        if (Cache::get($rateKey)) {
            $ttl = $this->cacheTtl($rateKey, 60);

            return ['success' => false, 'message' => __('app.api.service_verify_code.rate_limit', ['ttl' => $ttl]), 'expires_in' => max($ttl, 0)];
        }

        $code = $this->generateCode($length);
        $ttl = (int) config('sms.verification.ttl_seconds', 300);

        try {
            $result = app(SmsService::class)->sendVerificationCode($phone, $code);

            if (! ($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? __('app.api.service_verify_code.sms_failed'),
                    'expires_in' => 0,
                ];
            }

            Cache::put(self::CACHE_PREFIX."sms:{$phone}", $code, $ttl);
            Cache::put($rateKey, true, config('sms.verification.rate_limit_seconds', 60));

            return ['success' => true, 'message' => __('app.api.service_verify_code.code_sent'), 'expires_in' => $ttl];
        } catch (\Throwable $e) {
            Log::error("VerifyCode: sms send failed to {$phone}", ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => __('app.api.service_verify_code.send_failed'), 'expires_in' => 0];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyEmail(string $email, string $code, ?string $action = null, bool $expireOnUse = true): array
    {
        $cacheKey = $this->emailCodeKey($email, $action ?? 'verify');
        $cached = Cache::get($cacheKey);

        if (! $cached || $cached !== $code) {
            return ['success' => false, 'message' => __('app.api.service_verify_code.code_invalid')];
        }

        if ($expireOnUse) {
            Cache::forget($cacheKey);
        }

        return ['success' => true, 'message' => __('app.api.service_verify_code.verified')];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifySms(string $phone, string $code): array
    {
        $cacheKey = self::CACHE_PREFIX."sms:{$phone}";
        $cached = Cache::get($cacheKey);

        if (! $cached || $cached !== $code) {
            return ['success' => false, 'message' => __('app.api.service_verify_code.code_invalid')];
        }

        Cache::forget($cacheKey);

        return ['success' => true, 'message' => __('app.api.service_verify_code.verified')];
    }

    protected function generateCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }

        return $code;
    }

    private function emailCodeKey(string $email, string $action): string
    {
        return self::CACHE_PREFIX."email:{$email}:{$action}";
    }

    private function cacheTtl(string $key, int $default): int
    {
        try {
            return Cache::ttl($key);
        } catch (\Throwable) {
            return $default;
        }
    }
}
