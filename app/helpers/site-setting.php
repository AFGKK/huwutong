<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * 获取站点设置值
 *
 * 用法: site_setting('site_name', '默认值')
 * 缓存键 site_settings_all；测试中修改设置后需 Cache::forget('site_settings_all')
 *
 * 兼容别名：前台历史键名 ↔ 后台种子键名（任一侧有非空值即可）
 */
function site_setting(string $key, mixed $default = ''): mixed
{
    // 不使用 static：否则 Cache::forget 后进程内仍读到旧值，导致测试串扰
    $settings = Cache::remember('site_settings_all', 3600, function () {
        return SiteSetting::pluck('value', 'key')->toArray();
    });

    if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
        return $settings[$key];
    }

    $aliases = [
        'gongan_beian' => ['police_beian'],
        'gongan_beian_url' => ['police_beian_url'],
        'working_hours' => ['service_work_hours'],
        'police_beian' => ['gongan_beian'],
        'service_work_hours' => ['working_hours'],
        // 品牌主题色 ↔ 前端页面主题色（任一侧有值即可）
        'primary_color' => ['page_primary_color'],
        'page_primary_color' => ['primary_color'],
    ];

    foreach ($aliases[$key] ?? [] as $alias) {
        if (array_key_exists($alias, $settings) && $settings[$alias] !== null && $settings[$alias] !== '') {
            return $settings[$alias];
        }
    }

    return $default;
}

/**
 * 公开页展示备案号：过滤「演示」/全零占位，避免误导访客。
 */
function site_beian_public(string $key, mixed $default = ''): mixed
{
    $value = site_setting($key, $default);
    if (! is_string($value) || $value === '') {
        return $default;
    }
    if (str_contains($value, '演示') || preg_match('/0{6,}/', $value) === 1) {
        return $default;
    }

    return $value;
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
