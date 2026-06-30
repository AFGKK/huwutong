<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 验证码服务统一封装 (M2-66)
 *
 * 统一管理邮箱验证码和手机短信验证码的发送、校验、频率控制。
 * 支持多通道自动切换和降级。
 */
class VerifyCodeService
{
    const CACHE_PREFIX = 'verify_code:';
    const CACHE_TTL = 300; // 验证码缓存 5 分钟

    /**
     * 发送邮箱验证码
     *
     * @param string $email
     * @param string $action 验证场景: register/login/bind/reset_password
     * @param int|null $length 验证码长度，默认 6
     * @return array ['success' => bool, 'message' => string, 'expires_in' => int]
     */
    public function sendEmail(string $email, string $action = 'verify', ?int $length = 6): array
    {
        // 频率限制：同邮箱同场景 60 秒内不可重复发送
        $rateKey = self::CACHE_PREFIX . "rate:email:{$email}:{$action}";
        if (Cache::get($rateKey)) {
            try {
                $ttl = Cache::ttl($rateKey);
            } catch (\Throwable) {
                $ttl = 60;
            }
            return ['success' => false, 'message' => "请 {$ttl} 秒后再试", 'expires_in' => max($ttl, 0)];
        }

        $code = $this->generateCode($length);

        try {
            // 入库
            EmailVerification::create([
                'email' => $email,
                'code' => $code,
                'action' => $action,
                'expires_at' => now()->addMinutes(10),
            ]);

            // 发送邮件
            Mail::send('emails.verify-code', [
                'code' => $code,
                'action' => $action,
                'expires_in' => 10,
            ], function ($message) use ($email) {
                $message->to($email)->subject('验证码');
            });

            // 设置频率限制
            Cache::put($rateKey, true, 60);

            return ['success' => true, 'message' => '验证码已发送', 'expires_in' => 600];
        } catch (\Throwable $e) {
            Log::error("VerifyCode: email send failed to {$email}", ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => '发送失败，请稍后重试', 'expires_in' => 0];
        }
    }

    /**
     * 发送手机短信验证码（委托给 SmsService）
     *
     * @param string $phone
     * @param string $action
     * @param int|null $length
     * @return array
     */
    public function sendSms(string $phone, string $action = 'login', ?int $length = 6): array
    {
        // 频率限制
        $rateKey = self::CACHE_PREFIX . "rate:sms:{$phone}:{$action}";
        if (Cache::get($rateKey)) {
            try {
                $ttl = Cache::ttl($rateKey);
            } catch (\Throwable) {
                $ttl = 60;
            }
            return ['success' => false, 'message' => "请 {$ttl} 秒后再试", 'expires_in' => max($ttl, 0)];
        }

        $code = $this->generateCode($length);

        try {
            /** @var SmsService $smsService */
            $smsService = app(SmsService::class);
            $result = $smsService->sendVerificationCode($phone, $code);

            if (!$result['success']) {
                return ['success' => false, 'message' => $result['message'] ?? '短信发送失败', 'expires_in' => 0];
            }

            Cache::put($rateKey, true, 60);

            return ['success' => true, 'message' => '验证码已发送', 'expires_in' => 300];
        } catch (\Throwable $e) {
            Log::error("VerifyCode: sms send failed to {$phone}", ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => '发送失败，请稍后重试', 'expires_in' => 0];
        }
    }

    /**
     * 校验邮箱验证码
     *
     * @param string $email
     * @param string $code
     * @param string|null $action 可选，限定验证场景
     * @param bool $expireOnUse 验证通过后是否立即过期
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyEmail(string $email, string $code, ?string $action = null, bool $expireOnUse = true): array
    {
        $query = EmailVerification::where('email', $email)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->where('used', false);

        if ($action) {
            $query->where('action', $action);
        }

        $record = $query->latest()->first();

        if (!$record) {
            return ['success' => false, 'message' => '验证码无效或已过期'];
        }

        if ($expireOnUse) {
            $record->update(['used' => true]);
        }

        return ['success' => true, 'message' => '验证通过'];
    }

    /**
     * 校验手机验证码
     *
     * @param string $phone
     * @param string $code
     * @return array
     */
    public function verifySms(string $phone, string $code): array
    {
        $cacheKey = self::CACHE_PREFIX . "sms:{$phone}";

        $cached = Cache::get($cacheKey);
        if (!$cached || $cached !== $code) {
            return ['success' => false, 'message' => '验证码无效或已过期'];
        }

        Cache::forget($cacheKey);

        return ['success' => true, 'message' => '验证通过'];
    }

    /**
     * 生成验证码
     */
    protected function generateCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
}
