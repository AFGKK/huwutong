<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 将后台 SiteSetting 中的支付/短信/邮件/语言配置叠加到 runtime config。
 *
 * 优先级：.env 已有非空值保留；SiteSetting 仅填充空缺项，并在 .env 为 log/空时覆盖 driver。
 */
class SiteSettingRuntimeOverlay
{
    public static function apply(): void
    {
        try {
            if (! function_exists('site_setting')) {
                return;
            }

            if (! Schema::hasTable('site_settings')) {
                return;
            }

            self::overlayPayment();
            self::overlaySms();
            self::overlayMail();
            self::overlayLocale();
            self::overlaySecurity();
        } catch (Throwable $e) {
            Log::debug('SiteSettingRuntimeOverlay skipped: '.$e->getMessage());
        }
    }

    private static function overlaySecurity(): void
    {
        $timeout = (int) site_setting('security_session_timeout', 0);
        if ($timeout > 0) {
            // Sanctum expiration 单位为分钟
            config(['sanctum.expiration' => $timeout]);
        }
    }

    private static function overlayLocale(): void
    {
        $locale = trim((string) site_setting('default_locale', ''));
        if ($locale === '') {
            return;
        }
        $locale = str_replace('-', '_', $locale);
        if (strcasecmp($locale, 'zh_CN') === 0 || strcasecmp($locale, 'zh') === 0) {
            $locale = 'zh_CN';
        }
        if (in_array($locale, ['zh_CN', 'en'], true)) {
            config(['app.locale' => $locale]);
        }
    }

    private static function overlayMail(): void
    {
        self::fillIfEmpty('mail.mailers.smtp.host', site_setting('smtp_host', ''));
        $port = trim((string) site_setting('smtp_port', ''));
        if ($port !== '' && (env('MAIL_PORT') === null || env('MAIL_PORT') === '')) {
            config(['mail.mailers.smtp.port' => (int) $port]);
        }
        $enc = site_setting('smtp_encryption', null);
        if ($enc !== null && (env('MAIL_ENCRYPTION') === null || env('MAIL_ENCRYPTION') === '')) {
            config(['mail.mailers.smtp.encryption' => $enc === '' ? null : $enc]);
        }
        self::fillIfEmpty('mail.mailers.smtp.username', site_setting('smtp_username', ''));
        self::fillIfEmpty('mail.mailers.smtp.password', site_setting('smtp_password', ''));
        self::fillIfEmpty('mail.from.address', site_setting('mail_from_address', ''));
        self::fillIfEmpty('mail.from.name', site_setting('mail_from_name', ''));

        $driver = trim((string) site_setting('mail_driver', ''));
        if ($driver === '' || ! in_array($driver, ['log', 'smtp', 'mailgun', 'ses', 'sendmail', 'array'], true)) {
            return;
        }

        // .env 已配置正式邮件驱动时不覆盖；仅在默认 log / 未设置时采用 SiteSetting
        $envMailer = env('MAIL_MAILER');
        if ($envMailer === null || $envMailer === '' || $envMailer === 'log') {
            config(['mail.default' => $driver]);
        }
    }

    private static function overlayPayment(): void
    {
        $driver = trim((string) site_setting('payment_driver', ''));
        if ($driver !== '' && in_array($driver, ['mock', 'alipay', 'wechat', 'stripe', 'paypal', 'yipay'], true)) {
            config(['payment.driver' => $driver]);
        }

        self::fillIfEmpty('payment.channels.alipay.app_id', site_setting('alipay_app_id', ''));
        self::fillIfEmpty('payment.channels.alipay.private_key', site_setting('alipay_private_key', ''));
        self::fillIfEmpty('payment.channels.alipay.public_key', site_setting('alipay_public_key', ''));
        self::fillIfEmpty('payment.channels.alipay.notify_url', site_setting('alipay_notify_url', ''));
        if (site_setting('alipay_enabled', '0') === '1') {
            config(['payment.channels.alipay.enabled' => true]);
        }

        self::fillIfEmpty('payment.channels.wechat.app_id', site_setting('wechat_app_id', ''));
        self::fillIfEmpty('payment.channels.wechat.mch_id', site_setting('wechat_mch_id', ''));
        self::fillIfEmpty('payment.channels.wechat.key', site_setting('wechat_api_key', ''));
        self::fillIfEmpty('payment.channels.wechat.notify_url', site_setting('wechat_notify_url', ''));
        if (site_setting('wechat_enabled', '0') === '1') {
            config(['payment.channels.wechat.enabled' => true]);
        }

        self::fillIfEmpty('payment.channels.stripe.key', site_setting('stripe_public_key', ''));
        self::fillIfEmpty('payment.channels.stripe.secret', site_setting('stripe_secret_key', ''));
        self::fillIfEmpty('payment.channels.stripe.webhook_secret', site_setting('stripe_webhook_secret', ''));
        if (site_setting('stripe_enabled', '0') === '1') {
            config(['payment.channels.stripe.enabled' => true]);
        }

        self::fillIfEmpty('payment.channels.paypal.client_id', site_setting('paypal_client_id', ''));
        self::fillIfEmpty('payment.channels.paypal.client_secret', site_setting('paypal_client_secret', ''));
        $paypalMode = trim((string) site_setting('paypal_mode', ''));
        if ($paypalMode !== '') {
            config(['payment.channels.paypal.sandbox' => $paypalMode !== 'live']);
        }
        if (site_setting('paypal_enabled', '0') === '1') {
            config(['payment.channels.paypal.enabled' => true]);
        }

        self::fillIfEmpty('payment.channels.yipay.pid', site_setting('yipay_pid', ''));
        self::fillIfEmpty('payment.channels.yipay.key', site_setting('yipay_key', ''));
        self::fillIfEmpty('payment.channels.yipay.api_url', site_setting('yipay_api_url', ''));
        self::fillIfEmpty('payment.channels.yipay.notify_url', site_setting('yipay_notify_url', ''));
        self::fillIfEmpty('payment.channels.yipay.return_url', site_setting('yipay_return_url', ''));
        if (site_setting('yipay_enabled', '0') === '1') {
            config(['payment.channels.yipay.enabled' => true]);
        }
    }

    private static function overlaySms(): void
    {
        self::fillIfEmpty('sms.aliyun.access_key_id', site_setting('sms_aliyun_key', ''));
        self::fillIfEmpty('sms.aliyun.access_key_secret', site_setting('sms_aliyun_secret', ''));
        self::fillIfEmpty('sms.aliyun.sign_name', site_setting('sms_aliyun_sign', ''));
        self::fillIfEmpty('sms.aliyun.template_code', site_setting('sms_aliyun_template', ''));
        self::fillIfEmpty('sms.aliyun.notification_template_code', site_setting('sms_aliyun_notify_template', ''));

        $driver = trim((string) site_setting('sms_driver', ''));
        if ($driver === 'log') {
            config(['sms.driver' => 'log']);
        } elseif ($driver === 'aliyun') {
            $id = (string) config('sms.aliyun.access_key_id', '');
            $secret = (string) config('sms.aliyun.access_key_secret', '');
            if ($id !== '' && $secret !== '') {
                config(['sms.driver' => 'aliyun']);
            } else {
                config(['sms.driver' => 'log']);
            }
        } elseif ($driver === 'tencent') {
            // 腾讯云短信尚未接入 runtime，回退 log，避免假生效
            config(['sms.driver' => 'log']);
            Log::debug('SMS: tencent selected in SiteSetting; runtime falls back to log');
        }
    }

    private static function fillIfEmpty(string $configKey, mixed $settingValue): void
    {
        $settingValue = is_string($settingValue) ? trim($settingValue) : $settingValue;
        if ($settingValue === null || $settingValue === '') {
            return;
        }

        $current = config($configKey);
        if ($current === null || $current === '' || $current === false) {
            config([$configKey => $settingValue]);
        }
    }
}
