<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>快速开始 - 互物通 | 企业级授权管理系统</title>
<meta name="description" content="5 分钟完成互物通 SDK 集成，注册账户、安装 SDK、集成验证、生成 License">
<meta property="og:title" content="快速开始 - 互物通 | 企业级授权管理">
<meta property="og:description" content="5 分钟完成互物通 SDK 集成">
<meta property="og:type" content="website">
<link rel="canonical" href="{{ url('/docs/quickstart') }}">
@include('public.partials.tracking')
@vite('resources/css/public.css')
<style>.step-card:hover { transform: translateX(4px); } .step-card { transition: all 0.3s ease; }</style>
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
                <span class="text-gray-700 font-medium">快速开始</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">快速开始</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">5 分钟完成互物通 SDK 集成</p>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="space-y-8">
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-primary-600 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">1</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">注册账户</h2>
                        <p class="text-gray-600 mb-3">在互物通平台注册账户，创建您的第一个产品。</p>
                        <a href="/build/register" class="text-primary-600 font-medium hover:text-primary-700 transition text-sm">免费注册 →</a>
                    </div>
                </div>
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-primary-600 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">2</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">安装 SDK</h2>
                        <p class="text-gray-600 mb-3">选择您的编程语言，通过包管理器安装 SDK。</p>
                        <div class="bg-gray-50 rounded-xl p-4 mb-3"><code class="text-sm text-gray-800">composer require huwutong/sdk</code></div>
                        <a href="/sdk" class="text-primary-600 font-medium hover:text-primary-700 transition text-sm">查看所有 SDK →</a>
                    </div>
                </div>
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-primary-600 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">3</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">集成验证</h2>
                        <p class="text-gray-600 mb-3">复制以下代码，完成 License 验证集成。</p>
                        <div class="bg-gray-900 rounded-2xl p-6 shadow-xl">
                            <pre class="text-sm text-gray-200 font-mono leading-relaxed overflow-x-auto"><code><span class="text-blue-400">$client</span> = <span class="text-purple-400">new</span> <span class="text-green-400">HWT\Client</span>(<span class="text-orange-400">'your_api_key'</span>);
<span class="text-blue-400">$result</span> = <span class="text-blue-400">$client</span>-><span class="text-yellow-300">validate</span>(<span class="text-orange-400">'HWT-ENT-XXXX-XXXX'</span>);

<span class="text-gray-400">if</span> (<span class="text-blue-400">$result</span>-><span class="text-yellow-300">isValid</span>()) {
    <span class="text-gray-400">echo</span> <span class="text-green-400">"验证通过"</span>;
}</code></pre>
                        </div>
                    </div>
                </div>
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-primary-600 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">4</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">生成 License</h2>
                        <p class="text-gray-600">登录管理后台，为您的客户生成 License Key，全程自动化管理。</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20 text-white text-center" style="background:linear-gradient(135deg,#2563eb,#1d4ed8)">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl md:text-3xl font-bold mb-4">准备好了吗？</h2>
            <a href="/build/register" class="inline-block bg-white text-primary-600 px-8 py-3 rounded-xl font-bold hover:bg-primary-50 transition shadow-lg">免费开始 →</a>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
