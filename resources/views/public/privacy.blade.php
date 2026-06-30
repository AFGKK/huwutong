<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>隐私政策 - 互物通 | 企业级授权管理系统</title>
<meta name="description" content="互物通隐私政策——我们如何收集、使用和保护您的个人信息">
<meta property="og:title" content="隐私政策 - 互物通 | 企业级授权管理">
<meta property="og:description" content="了解互物通如何收集、使用和保护您的个人信息">
<meta property="og:type" content="website">
<link rel="canonical" href="{{ url('/privacy') }}">
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
                <span class="text-gray-700 font-medium">隐私政策</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl font-extrabold text-gray-900 mb-4">隐私政策</h1>
                <p class="text-gray-500">最后更新：2026 年 1 月 1 日</p>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-gray">
            <h2>信息收集</h2>
            <p>我们收集您在使用互物通服务时提供的信息，包括但不限于：姓名、邮箱地址、公司名称、支付信息等。我们仅收集提供服务所必需的信息。</p>
            <h2>信息使用</h2>
            <p>我们使用收集的信息用于：提供和维护服务、处理交易、发送服务通知、改善用户体验、以及法律要求的合规目的。</p>
            <h2>信息共享</h2>
            <p>我们不会将您的个人信息出售给第三方。我们可能与信任的第三方服务提供商共享必要的信息，以便他们代表我们提供服务（如支付处理、邮件发送等）。</p>
            <h2>数据安全</h2>
            <p>我们采用业界领先的安全措施保护您的数据，包括 SSL/TLS 加密传输、静态数据加密、访问控制审计等。</p>
            <h2>您的权利</h2>
            <p>您有权访问、更正、删除您的个人数据，以及限制或反对数据处理。您可以通过联系我们来行使这些权利。</p>
            <h2>Cookie</h2>
            <p>我们使用必要的Cookie 来确保服务正常运行。您可以随时通过浏览器设置管理Cookie 偏好。</p>
            <h2>联系我们</h2>
            <p>如对隐私政策有任何疑问，请通过 <a href="/contact">联系我们</a> 页面与我们取得联系。</p>
        </div>
    </section>

    @include('public.partials.footer')
</body>
</html>
