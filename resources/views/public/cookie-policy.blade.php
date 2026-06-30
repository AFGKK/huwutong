<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cookie 政策 - 了解我们如何使用 Cookie">
    <meta name="robots" content="index, follow">
    <title>Cookie 政策 | {{ site_setting('site_name', '互物通') }}</title>
    @vite('resources/css/public.css')
    </head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    @include('public.partials.nav')

    <main class="flex-1 pt-28 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="animate-fade-in mb-10 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-2xl mb-4">
                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">Cookie 政策</h1>
                <p class="text-lg text-gray-500">最后更新：2026 年6 月</p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-10 space-y-8 text-gray-700 leading-relaxed animate-slide-up">
                
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">1. 什么是 Cookie</h2>
                    <p>Cookie 是当您访问网站时，存储在您浏览器或设备上的小型文本文件。它们被广泛用于使网站更高效地运行，以及向网站所有者提供信息。</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">2. 我们使用的Cookie 类型</h2>
                    <p class="mb-4">我们将Cookie 分为以下四类：</p>
                    <div class="space-y-4">
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                            <h3 class="font-semibold text-blue-800">🍪 必要 Cookies</h3>
                            <p class="text-sm text-blue-700 mt-1">这些 Cookie 是网站运行所必需的，无法关闭。它们通常用于处理登录、会话保持和安全验证等基本功能。</p>
                            <p class="text-xs text-blue-500 mt-1">始终启用</p>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4 border border-green-100">
                            <h3 class="font-semibold text-green-800">⚙️ 功能 Cookies</h3>
                            <p class="text-sm text-green-700 mt-1">这些 Cookie 用于记住您的偏好设置（如语言、主题、区域），以提供个性化的使用体验。</p>
                        </div>
                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                            <h3 class="font-semibold text-purple-800">📊 分析 Cookies</h3>
                            <p class="text-sm text-purple-700 mt-1">这些 Cookie 收集匿名使用数据（如页面访问量、点击流），帮助我们了解用户如何与我们的产品互动，从而改进服务。</p>
                        </div>
                        <div class="bg-orange-50 rounded-xl p-4 border border-orange-100">
                            <h3 class="font-semibold text-orange-800">📢 营销 Cookies</h3>
                            <p class="text-sm text-orange-700 mt-1">这些 Cookie 用于跟踪您的浏览习惯，以提供与您相关的广告和营销内容。</p>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">3. 我们使用的具佀Cookie 清单</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left px-4 py-3 border-b font-semibold text-gray-700">Cookie 名称</th>
                                    <th class="text-left px-4 py-3 border-b font-semibold text-gray-700">分类</th>
                                    <th class="text-left px-4 py-3 border-b font-semibold text-gray-700">用途</th>
                                    <th class="text-left px-4 py-3 border-b font-semibold text-gray-700">有效期</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs">XSRF-TOKEN</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">必要</span></td>
                                    <td class="px-4 py-3">CSRF 防护令牌</td>
                                    <td class="px-4 py-3">会话</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs">laravel_session</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">必要</span></td>
                                    <td class="px-4 py-3">会话标识</td>
                                    <td class="px-4 py-3">会话</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs">cookie_consent</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">必要</span></td>
                                    <td class="px-4 py-3">记录 Cookie 同意偏好</td>
                                    <td class="px-4 py-3">365 夀</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs">cookie_consent_given</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">必要</span></td>
                                    <td class="px-4 py-3">Cookie 同意详细记录</td>
                                    <td class="px-4 py-3">365 夀</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">注：实际使用的Cookie 可能因版本更新有所变化。我们建议您定期查看本政策。</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">4. 如何管理 Cookie</h2>
                    <p class="mb-3">您可以通过以下方式管理 Cookie 偏好：</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li><strong>网站设置面板</strong> —点击页面右下角的 🍪 按钮，随时打开 Cookie 设置面板，自定义您的偏好　</li>
                        <li><strong>浏览器设罀</strong> —您可以通过浏览器设置阻止或删除 Cookie。不同浏览器的设置方法不同，请参考浏览器的帮助文档　</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">5. 第三斀Cookie</h2>
                    <p>我们可能会使用以下第三方服务，这些服务可能会在您的设备上设置 Cookie。</p>
                    <ul class="list-disc pl-6 space-y-1 mt-2">
                        <li>分析服务（如 Google Analytics） 用于了解网站使用情况</li>
                        <li>客户支持工具 —用于提供在线客服功能</li>
                    </ul>
                    <p class="mt-3">这些第三方服务仅在您同意相关 Cookie 分类后才会加载。</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">6. 政策更新</h2>
                    <p>我们可能会不时更新本 Cookie 政策。如有重大变更，我们会通过网站通知或在您下次访问时重新获取您的同意。</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">7. 联系我们</h2>
                    <p>如果您对本Cookie 政策有任何疑问，请通过以下方式联系我们：</p>
                    <ul class="list-disc pl-6 space-y-1 mt-2">
                        <li>邮箱：<a href="mailto:support@huwutong.com">support@huwutong.com</a></li>
                        <li>在线客服：点击页面右下角的客服图标</li>
                    </ul>
                </section>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
</body>
</html>
