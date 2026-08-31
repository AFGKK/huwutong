<?php

namespace App\Console\Commands;

use App\Mail\VerifyCodeMail;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * D-04: 邮件/短信生产配置冒烟测试
 *
 * php artisan notifications:test --email=you@example.com --phone=13800138000
 */
class NotificationsTestCommand extends Command
{
    protected $signature = 'notifications:test
        {--email= : 测试收件邮箱}
        {--phone= : 测试手机号}
        {--skip-mail : 跳过邮件测试}
        {--skip-sms : 跳过短信测试}';

    protected $description = '测试邮件/短信生产配置是否可用（D-04）';

    public function handle(SmsService $smsService): int
    {
        $this->info('=== D-04 邮件/短信配置检测 ===');
        $this->line('');

        $mailOk = true;
        $smsOk = true;

        if (! $this->option('skip-mail')) {
            $mailOk = $this->testMail();
        }

        if (! $this->option('skip-sms')) {
            $smsOk = $this->testSms($smsService);
        }

        $this->line('');
        if ($mailOk && $smsOk) {
            $this->info('结果: 通过');

            return self::SUCCESS;
        }

        $this->error('结果: 未通过');

        return self::FAILURE;
    }

    private function testMail(): bool
    {
        $mailer = config('mail.default');
        $from = config('mail.from.address');
        $email = $this->option('email');

        $this->line("邮件驱动: {$mailer}");
        $this->line("发件地址: {$from}");

        if ($mailer === 'log' && app()->environment('production')) {
            $this->warn('  生产环境 MAIL_MAILER 仍为 log，请改为 smtp/ses/postmark');

            return false;
        }

        if (! $email) {
            $this->warn('  未指定 --email，跳过实际发送');

            return $mailer !== 'log' || ! app()->environment('production');
        }

        try {
            Mail::to($email)->send(new VerifyCodeMail('123456', 'login', 10));
            $this->info("  已发送测试邮件 → {$email}");

            return true;
        } catch (\Throwable $e) {
            $this->error('  邮件发送失败: '.$e->getMessage());

            return false;
        }
    }

    private function testSms(SmsService $smsService): bool
    {
        $driver = config('sms.driver');
        $this->line("短信驱动: {$driver}");

        if ($driver === 'log' && app()->environment('production')) {
            $this->warn('  生产环境 SMS_DRIVER 仍为 log，请改为 aliyun');

            return false;
        }

        if ($driver === 'aliyun') {
            $cfg = config('sms.aliyun');
            foreach (['access_key_id', 'access_key_secret', 'template_code'] as $key) {
                if (empty($cfg[$key])) {
                    $this->error("  缺少配置 sms.aliyun.{$key}");

                    return false;
                }
            }
        }

        $phone = $this->option('phone');
        if (! $phone) {
            $this->warn('  未指定 --phone，跳过实际发送');

            return $driver !== 'log' || ! app()->environment('production');
        }

        $result = $smsService->sendVerificationCode($phone, '888888');

        if ($result['success'] ?? false) {
            $this->info("  短信发送成功 (driver={$result['driver']})");

            return true;
        }

        $this->error('  短信发送失败: '.($result['message'] ?? 'unknown'));

        return false;
    }
}
