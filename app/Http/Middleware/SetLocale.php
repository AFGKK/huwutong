<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-22: 多语言中间件
 *
 * 按优先级检测用户语言偏好：
 *   1. URL 参数 ?lang=zh_CN
 *   2. Cookie locale（用户手动切换后设置的）
 *   3. Accept-Language 请求头
 *   4. Session locale
 *   5. 已认证用户的 profile->locale
 *   6. 应用默认 locale
 */
class SetLocale
{
    /** 支持的语言列表 */
    protected array $supportedLocales = ['zh_CN', 'en'];

    /** 语言名称映射（前端展示用） */
    public static array $localeNames = [
        'zh_CN' => '简体中文',
        'en' => 'English',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        view()->share('currentLocale', $locale);
        view()->share('localeNames', static::$localeNames);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Locale', $locale);

        return $response;
    }

    protected function resolveLocale(Request $request): string
    {
        // 1. URL 参数 ?lang=zh_CN 或 ?lang=en
        if ($request->has('lang')) {
            $lang = $this->normalizeLocale((string) $request->input('lang'));
            if ($this->isValidLocale($lang)) {
                Cookie::queue('locale', $lang, 60 * 24 * 365);

                return $lang;
            }
        }

        // 2. Cookie
        if ($cookieLang = $request->cookie('locale')) {
            $cookieLang = $this->normalizeLocale((string) $cookieLang);
            if ($this->isValidLocale($cookieLang)) {
                return $cookieLang;
            }
        }

        // 3. Accept-Language 头
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $preferred = $this->parseAcceptLanguage($acceptLanguage);
            if ($preferred && $this->isValidLocale($preferred)) {
                return $preferred;
            }
        }

        // 4. Session
        if ($sessionLang = session('locale')) {
            $sessionLang = $this->normalizeLocale((string) $sessionLang);
            if ($this->isValidLocale($sessionLang)) {
                return $sessionLang;
            }
        }

        // 5. 已认证用户的偏好
        if ($request->user() && $request->user()->locale) {
            $userLocale = $this->normalizeLocale((string) $request->user()->locale);
            if ($this->isValidLocale($userLocale)) {
                return $userLocale;
            }
        }

        // 6. 站点设置 default_locale（兼容 zh-CN / zh_CN）
        if (function_exists('site_setting')) {
            $siteLocale = $this->normalizeLocale((string) site_setting('default_locale', ''));
            if ($siteLocale !== '' && $this->isValidLocale($siteLocale)) {
                return $siteLocale;
            }
        }

        // 7. 应用默认 locale
        return $this->normalizeLocale((string) config('app.locale', 'zh_CN'));
    }

    protected function parseAcceptLanguage(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        $locales = [];
        foreach (explode(',', $header) as $entry) {
            $parts = explode(';', trim($entry));
            $lang = $parts[0];
            $quality = 1.0;
            if (isset($parts[1]) && str_starts_with($parts[1], 'q=')) {
                $quality = (float) substr($parts[1], 2);
            }
            $locales[$lang] = $quality;
        }
        arsort($locales);

        $preferred = array_key_first($locales);

        return $this->normalizeLocale((string) $preferred);
    }

    /**
     * 规范化语言码：zh-CN / zh → zh_CN
     */
    protected function normalizeLocale(string $locale): string
    {
        $locale = trim(str_replace('-', '_', $locale));
        if ($locale === '') {
            return '';
        }

        if (! str_contains($locale, '_')) {
            $map = [
                'zh' => 'zh_CN',
                'en' => 'en',
            ];

            return $map[$locale] ?? $locale;
        }

        // zh_cn → zh_CN
        if (strcasecmp($locale, 'zh_CN') === 0 || strcasecmp($locale, 'zh_Hans') === 0) {
            return 'zh_CN';
        }

        return $locale;
    }

    protected function isValidLocale(string $locale): bool
    {
        return in_array($this->normalizeLocale($locale), $this->supportedLocales, true);
    }
}
