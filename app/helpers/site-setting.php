<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * 获取站点设置值
 * 
 * 用法: site_setting('site_name', '默认值')
 */
function site_setting(string $key, mixed $default = ''): mixed
{
    static $settings = null;

    if ($settings === null) {
        $settings = Cache::remember('site_settings_all', 3600, function () {
            return SiteSetting::pluck('value', 'key')->toArray();
        });
    }

    return $settings[$key] ?? $default;
}

if (! function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (! function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
