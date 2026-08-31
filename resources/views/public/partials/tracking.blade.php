<!-- ─── SEO / 跟踪 / 验证 代码 (动态管理，仅在用户同意后加载) ─── -->
@include('public.partials.theme-vars')
@php
    $faviconUrl = site_setting('favicon_url') ?: '/images/favicon.svg';
    $seoRobots = site_setting('seo_robots_meta', 'index,follow');
    $seoOgImage = site_setting('seo_og_image_default');
    $seoJsonLd = site_setting('seo_json_ld');
    $canonicalDomain = rtrim((string) site_setting('seo_canonical_domain', ''), '/');
@endphp
<link rel="icon" href="{{ $faviconUrl }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ site_setting('logo_url') ?: $faviconUrl }}">

@if($seoRobots)
<meta name="robots" content="{{ $seoRobots }}">
@endif

@if($seoOgImage)
<meta property="og:image" content="{{ $seoOgImage }}">
@endif

@if($canonicalDomain !== '')
<link rel="canonical" href="{{ $canonicalDomain . request()->getPathInfo() }}">
@endif

@if($seoJsonLd)
<script type="application/ld+json">{!! $seoJsonLd !!}</script>
@endif

@if(site_setting('seo_google_analytics'))
<script id="tracking-ga-data" type="application/json" data-id="{{ site_setting('seo_google_analytics') }}"></script>
@endif

@if(site_setting('tracking_baidu_id'))
<script id="tracking-baidu-data" type="application/json" data-id="{{ site_setting('tracking_baidu_id') }}"></script>
@endif

@if(site_setting('tracking_meta_pixel'))
<script id="tracking-meta-data" type="application/json" data-id="{{ site_setting('tracking_meta_pixel') }}"></script>
@endif

@if(site_setting('verify_google'))
<meta name="google-site-verification" content="{{ site_setting('verify_google') }}">
@endif

@if(site_setting('verify_baidu'))
<meta name="baidu-site-verification" content="{{ site_setting('verify_baidu') }}">
@endif

@if(site_setting('verify_bing'))
<meta name="msvalidate.01" content="{{ site_setting('verify_bing') }}">
@endif

@if(site_setting('custom_head_html'))
{{-- 自定义 Head 代码 --}}
{!! site_setting('custom_head_html') !!}
@endif

<script>
(function() {
    // 跟踪脚本加载器 — 由 cookieConsentChanged 事件触发
    var GIVEN_KEY = 'cookie_consent_given';
    var loaded = {};

    function getConsent() {
        try {
            var raw = localStorage.getItem(GIVEN_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch(e) { return null; }
    }

    function shouldLoad(category) {
        var data = getConsent();
        if (!data || data.action !== 'accepted') return false;
        return data.categories.indexOf(category) !== -1;
    }

    function loadGoogleAnalytics(id) {
        if (!shouldLoad('analytics')) return;
        if (loaded['ga']) return;
        loaded['ga'] = true;

        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', id);

        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
        document.head.appendChild(s);
    }

    function loadBaidu(id) {
        if (!shouldLoad('analytics')) return;
        if (loaded['baidu']) return;
        loaded['baidu'] = true;

        var hm = document.createElement('script');
        hm.src = 'https://hm.baidu.com/hm.js?' + id;
        var s = document.getElementsByTagName('script')[0];
        s.parentNode.insertBefore(hm, s);
    }

    function loadMetaPixel(id) {
        if (!shouldLoad('marketing')) return;
        if (loaded['meta']) return;
        loaded['meta'] = true;

        !function(f,b,e,v,n,t,s) {
            if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)
        }(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');

        fbq('init', id);
        fbq('track', 'PageView');
    }

    function applyAll() {
        var gaEl = document.getElementById('tracking-ga-data');
        if (gaEl) loadGoogleAnalytics(gaEl.dataset.id);

        var baiduEl = document.getElementById('tracking-baidu-data');
        if (baiduEl) loadBaidu(baiduEl.dataset.id);

        var metaEl = document.getElementById('tracking-meta-data');
        if (metaEl) loadMetaPixel(metaEl.dataset.id);
    }

    applyAll();

    document.addEventListener('cookieConsentChanged', function() {
        applyAll();
    });
})();
</script>
