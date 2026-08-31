{{-- 官网主题色：读取后台品牌设置，全站 CSS 变量 --}}
@php
    $pgPrimary = (string) site_setting('page_primary_color', site_setting('primary_color', '#0f172a'));
    if ($pgPrimary === '' || ! preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $pgPrimary)) {
        $pgPrimary = '#0f172a';
    }
    $hex = ltrim($pgPrimary, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $pgR = hexdec(substr($hex, 0, 2));
    $pgG = hexdec(substr($hex, 2, 2));
    $pgB = hexdec(substr($hex, 4, 2));
    $pgBg = site_setting('page_background', '#f9fafb') ?: '#f9fafb';
    $pgContentBg = site_setting('page_content_bg', '#ffffff') ?: '#ffffff';
    $pgFontSize = site_setting('page_font_size', '16px') ?: '16px';
@endphp
<meta name="theme-color" content="{{ $pgPrimary }}">
<style id="hwt-public-theme">
:root {
    --pg-primary: {{ $pgPrimary }};
    --pg-primary-rgb: {{ $pgR }}, {{ $pgG }}, {{ $pgB }};
    --pg-primary-50: color-mix(in srgb, var(--pg-primary) 10%, white);
    --pg-primary-100: color-mix(in srgb, var(--pg-primary) 20%, white);
    --pg-primary-200: color-mix(in srgb, var(--pg-primary) 35%, white);
    --pg-primary-300: color-mix(in srgb, var(--pg-primary) 50%, white);
    --pg-primary-400: color-mix(in srgb, var(--pg-primary) 70%, white);
    --pg-primary-500: color-mix(in srgb, var(--pg-primary) 85%, white);
    --pg-primary-700: color-mix(in srgb, var(--pg-primary) 85%, black);
    --pg-primary-800: color-mix(in srgb, var(--pg-primary) 70%, black);
    --pg-primary-900: color-mix(in srgb, var(--pg-primary) 55%, black);
    --pg-bg: {{ $pgBg }};
    --pg-content-bg: {{ $pgContentBg }};
    --pg-font-size: {{ $pgFontSize }};
}
/* 官网品牌按钮 / 实心块：Tailwind slate-900 跟随后台主色 */
.bg-slate-900 { background-color: var(--pg-primary) !important; }
.hover\:bg-slate-800:hover { background-color: var(--pg-primary-800) !important; }
.hover\:bg-slate-900:hover { background-color: var(--pg-primary) !important; }
.border-slate-900 { border-color: var(--pg-primary) !important; }
.from-slate-800 { --tw-gradient-from: var(--pg-primary-800) var(--tw-gradient-from-position); --tw-gradient-to: rgb(var(--pg-primary-rgb) / 0) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.to-slate-950 { --tw-gradient-to: var(--pg-primary-900) var(--tw-gradient-to-position); }
.from-slate-900 { --tw-gradient-from: var(--pg-primary) var(--tw-gradient-from-position); --tw-gradient-to: rgb(var(--pg-primary-rgb) / 0) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
</style>
