<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>服务条款 - 互物通 | 企业级授权管理系统</title>
<meta name="description" content="互物通服务条款——使用互物通服务需遵守的条款与条件">
<meta property="og:title" content="服务条款 - 互物通 | 企业级授权管理">
<meta property="og:description" content="使用互物通服务需遵守的条款与条件">
<meta property="og:type" content="website">
<link rel="canonical" href="{{ url('/terms') }}">
@include('public.partials.tracking')
@vite('resources/css/public.css')
<style>.prose h2 { font-size:1.5rem; font-weight:700; margin-top:2rem; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e5e7eb; } .prose p { margin-bottom:1rem; line-height:1.75; color:#4b5563; }</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')
    <section class="pt-24 pb-16 md:pb-20 bg-gradient-to-br from-primary-50 via-white to-blue-50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-primary-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-400 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <nav class="flex items-center gap-1.5 text-sm mb-8" style="color:rgba(107,114,128,0.8)">
                <a href="{{ url('/') }}" class="hover:text-primary-600 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    首页
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-700 font-medium">服务条款</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl font-extrabold text-gray-900 mb-4">服务条款</h1>
                <p class="text-gray-500">最后更新：2026 年 1 月 1 日</p>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-gray">
            <h2>服务说明</h2>
            <p>互物通（以下简称本服务）提供企业级软件授权管理解决方案，包括License 生成与验证、客户管理、支付处理、数据分析等功能。</p>
            <h2>账户注册</h2>
            <p>使用本服务需要注册账户。您必须提供准确、完整的信息，并负责维护账户安全。您对账户下的所有活动负责。</p>
            <h2>使用限制</h2>
            <p>您同意不会：滥用本服务进行非法活动；尝试破解或逆向工程本服务；超出授权范围使用本服务；干扰其他用户正常使用。</p>
            <h2>付费条款</h2>
            <p>付费方案按月度或年度计费。未按时付款可能导致服务暂停。具体计费规则以购买时展示的方案为准。</p>
            <h2>SLA 服务等级</h2>
            <p>我们承诺 99.99% 的服务可用性。如未达到SLA 标准，您可根据服务信用政策获得补偿。</p>
            <h2>免责声明</h2>
            <p>本服务按"现状"提供。在法律允许的最大范围内，我们不对因使用本服务产生的间接损失承担责任。</p>
        </div>
    </section>

    @include('public.partials.footer')
</body>
</html>
