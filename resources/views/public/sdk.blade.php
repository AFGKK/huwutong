<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SDK 下载 - 互物通 | 企业级授权管理系统</title>
<meta name="description" content="下载互物通 SDK，支持 PHP、Node.js、Python、Go、Java、C#，3 行代码完成授权验证集成">
<meta property="og:title" content="SDK 下载 - 互物通 | 企业级授权管理">
<meta property="og:description" content="多语言 SDK 下载，快速集成 License 授权验证">
<meta property="og:type" content="website">
<link rel="canonical" href="{{ url('/sdk') }}">
@include('public.partials.tracking')
@vite('resources/css/public.css')
<style>.sdk-card{transition:all .3s ease}.sdk-card:hover{transform:translateY(-4px);box-shadow:0 12px 24px -8px rgba(59,130,246,.15)}</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')
    <section class="pt-24 pb-16 md:pb-20 bg-gradient-to-br from-primary-50 via-white to-blue-50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-primary-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-400 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <nav class="flex items-center gap-1.5 text-sm mb-8" style="color:rgba(107,114,128,0.8)">
                <a href="{{ url('/') }}" class="hover:text-primary-600 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    首页
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-700 font-medium">SDK 下载</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">SDK 下载</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">选择您的编程语言，3 行代码完成授权验证集成</p>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="sdk-card bg-white rounded-xl border border-gray-100 p-6">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center mb-4"><span class="text-2xl font-bold text-blue-600">PHP</span></div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">PHP SDK</h3>
                    <p class="text-sm text-gray-500 mb-4">适用于Laravel / Symfony / ThinkPHP 等框架</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4"><code class="text-sm text-gray-800">composer require huwutong/sdk</code></div>
                    <a href="/docs/sdk/php" class="text-primary-600 font-medium hover:text-primary-700 transition">查看文档 →</a>
                </div>
                <div class="sdk-card bg-white rounded-xl border border-gray-100 p-6">
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-4"><span class="text-2xl font-bold text-green-600">JS</span></div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Node.js SDK</h3>
                    <p class="text-sm text-gray-500 mb-4">适用于Express / Koa / Next.js 等框架</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4"><code class="text-sm text-gray-800">npm install huwutong-sdk</code></div>
                    <a href="/docs/sdk/js" class="text-primary-600 font-medium hover:text-primary-700 transition">查看文档 →</a>
                </div>
                <div class="sdk-card bg-white rounded-xl border border-gray-100 p-6">
                    <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center mb-4"><span class="text-2xl font-bold text-yellow-600">Py</span></div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Python SDK</h3>
                    <p class="text-sm text-gray-500 mb-4">适用于Django / Flask / FastAPI 等框架</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4"><code class="text-sm text-gray-800">pip install huwutong-sdk</code></div>
                    <a href="/docs/sdk/python" class="text-primary-600 font-medium hover:text-primary-700 transition">查看文档 →</a>
                </div>
                <div class="sdk-card bg-white rounded-xl border border-gray-100 p-6">
                    <div class="w-12 h-12 bg-cyan-50 rounded-xl flex items-center justify-center mb-4"><span class="text-2xl font-bold text-cyan-600">Go</span></div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Go SDK</h3>
                    <p class="text-sm text-gray-500 mb-4">适用于Gin / Echo / Fiber 等框架</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4"><code class="text-sm text-gray-800">go get huwutong.com/sdk</code></div>
                    <a href="/docs/sdk/go" class="text-primary-600 font-medium hover:text-primary-700 transition">查看文档 →</a>
                </div>
                <div class="sdk-card bg-white rounded-xl border border-gray-100 p-6">
                    <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center mb-4"><span class="text-2xl font-bold text-red-600">Java</span></div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Java SDK</h3>
                    <p class="text-sm text-gray-500 mb-4">适用于Spring Boot / Micronaut 等框架</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4"><code class="text-sm text-gray-800">// Maven: com.huwutong:sdk:1.0</code></div>
                    <a href="/docs/sdk/java" class="text-primary-600 font-medium hover:text-primary-700 transition">查看文档 →</a>
                </div>
                <div class="sdk-card bg-white rounded-xl border border-gray-100 p-6">
                    <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center mb-4"><span class="text-2xl font-bold text-purple-600">C#</span></div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">C# SDK</h3>
                    <p class="text-sm text-gray-500 mb-4">适用于.NET Core / ASP.NET 等框架</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4"><code class="text-sm text-gray-800">Install-Package HWT.Sdk</code></div>
                    <a href="/docs/sdk/csharp" class="text-primary-600 font-medium hover:text-primary-700 transition">查看文档 →</a>
                </div>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20 bg-gray-50" id="examples">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">一分钟集成示例</h2>
            <div class="bg-gray-900 rounded-2xl p-8 shadow-xl">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                    <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                    <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                    <span class="text-gray-400 text-sm ml-2">PHP 示例</span>
                </div>
                <pre class="text-sm text-gray-200 font-mono leading-relaxed overflow-x-auto"><code><span class="text-purple-400">require</span> <span class="text-orange-400">'vendor/autoload.php'</span>;

<span class="text-blue-400">$client</span> = <span class="text-purple-400">new</span> <span class="text-green-400">HWT\Client</span>([
    <span class="text-orange-400">'api_key'</span> => <span class="text-orange-400">'your_api_key'</span>,
]);

<span class="text-blue-400">$result</span> = <span class="text-blue-400">$client</span>-><span class="text-yellow-300">validate</span>(<span class="text-orange-400">'HWT-ENT-XXXX-XXXX'</span>);

<span class="text-gray-400">if</span> (<span class="text-blue-400">$result</span>-><span class="text-yellow-300">isValid</span>()) {
    <span class="text-gray-400">echo</span> <span class="text-green-400">"License 有效"</span>;
} <span class="text-gray-400">else</span> {
    <span class="text-gray-400">echo</span> <span class="text-green-400">"License 无效: "</span> . <span class="text-blue-400">$result</span>-><span class="text-yellow-300">getError</span>();
}</code></pre>
            </div>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
