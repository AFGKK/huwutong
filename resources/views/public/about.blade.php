<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>关于我们 - 互物通 | 企业级授权管理系统</title>
    <meta name="description" content="了解互物通团队——致力于为全球软件开发者提供安全可靠的授权管理解决方案。Ed25519 签名、离线验证、多平台 SDK。">
    <meta property="og:title" content="关于我们 - 互物通 | 企业级授权管理">
    <meta property="og:description" content="了解互物通团队，让软件授权管理变得简单、安全、可靠">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/about') }}">
    @include('public.partials.tracking')
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "互物通",
        "url": "{{ url('/') }}",
        "description": "企业级授权管理系统",
        "foundingDate": "2024",
        "founder": { "@type": "Person", "name": "互物通团队" },
        "address": { "@type": "PostalAddress", "addressCountry": "CN" }
    }
    </script>
    @vite('resources/css/public.css')
    <style>
        .value-card { transition: all 0.3s ease; }
        .value-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(59,130,246,0.15); }
        .stat-item { transition: all 0.3s ease; }
        .stat-item:hover { transform: scale(1.05); }
        .timeline-line { position: relative; }
        .timeline-line::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, #3b82f6, #93c5fd); }
        .timeline-dot { width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: bold; position: relative; z-index: 1; flex-shrink: 0; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')

    <!-- ─── Hero ─── -->
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
                <span class="text-gray-700 font-medium">关于我们</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">关于互物通</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">让软件授权管理变得简单、安全、可靠</p>
            </div>
        </div>
    </section>

    <!-- ─── 统计数据 ─── -->
    <section class="py-12 bg-white border-b border-gray-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="stat-item">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent">10,000+</div>
                    <div class="text-sm text-gray-500 mt-1.5">活跃客户</div>
                </div>
                <div class="stat-item">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent">500万+</div>
                    <div class="text-sm text-gray-500 mt-1.5">License 生成</div>
                </div>
                <div class="stat-item">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent">99.99%</div>
                    <div class="text-sm text-gray-500 mt-1.5">服务可用性</div>
                </div>
                <div class="stat-item">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent">&lt;10ms</div>
                    <div class="text-sm text-gray-500 mt-1.5">平均验证延迟</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 使命 ─── -->
    <section class="py-16 md:py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">我们的使命</h2>
                <p class="text-lg text-gray-600 max-w-3xl mx-auto leading-relaxed">
                    互物通致力于为全球软件开发者提供一站式的企业级授权管理解决方案。
                    从独立开发者到跨国企业，我们帮助各类软件团队安全、高效地管理软件授权，
                    让开发者专注于产品创新，而非授权基础设施。
                </p>
            </div>
        </div>
    </section>

    <!-- ─── 核心价值 ─── -->
    <section class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">核心价值</h2>
                <p class="text-lg text-gray-600">我们坚持的四大原则</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="value-card p-8 rounded-2xl border border-gray-100 bg-white text-center">
                    <div class="w-14 h-14 bg-primary-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">安全第一</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Ed25519 签名算法 + 离线验证 + CRL 吊销列表，银行级安全保障</p>
                </div>
                <div class="value-card p-8 rounded-2xl border border-gray-100 bg-white text-center">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">开发者体验</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">3 行代码集成 SDK，多语言支持，一分钟即可上线运行</p>
                </div>
                <div class="value-card p-8 rounded-2xl border border-gray-100 bg-white text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">全球合规</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">GDPR / PIPL / SOC2 / ISO27001 合规，满足全球各地法律法规</p>
                </div>
                <div class="value-card p-8 rounded-2xl border border-gray-100 bg-white text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">持续创新</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">AI 驱动分析 + 边缘节点加速，不断推进行业技术边界</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 发展历程 ─── -->
    <section class="py-16 md:py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">发展历程</h2>
                <p class="text-lg text-gray-600">从构想到成长，每一步都脚踏实地</p>
            </div>
            <div class="space-y-8 timeline-line pl-10">
                <div class="flex items-start gap-4">
                    <div class="timeline-dot">1</div>
                    <div>
                        <div class="text-sm font-bold text-primary-600">2024 Q1</div>
                        <h3 class="text-lg font-semibold text-gray-900">项目启动</h3>
                        <p class="text-sm text-gray-500 mt-1">团队组建完成，核心架构设计，确定 Ed25519 签名方案</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot">2</div>
                    <div>
                        <div class="text-sm font-bold text-primary-600">2024 Q2</div>
                        <h3 class="text-lg font-semibold text-gray-900">Alpha 内测</h3>
                        <p class="text-sm text-gray-500 mt-1">首批 50 家内测用户，验证离线验证和 SDK 集成流程</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot">3</div>
                    <div>
                        <div class="text-sm font-bold text-primary-600">2024 Q3</div>
                        <h3 class="text-lg font-semibold text-gray-900">正式上线</h3>
                        <p class="text-sm text-gray-500 mt-1">v1.0 发布，支持 PHP/Node.js/Python SDK，注册用户突破 1,000</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot">4</div>
                    <div>
                        <div class="text-sm font-bold text-primary-600">2025</div>
                        <h3 class="text-lg font-semibold text-gray-900">快速成长</h3>
                        <p class="text-sm text-gray-500 mt-1">新增 Go/Java/C# SDK，AI 风控上线，活跃客户突破 10,000</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot">5</div>
                    <div>
                        <div class="text-sm font-bold text-primary-600">2026</div>
                        <h3 class="text-lg font-semibold text-gray-900">全面升级</h3>
                        <p class="text-sm text-gray-500 mt-1">v2.0 发布，边缘授权、IM 在线客服、AI 智能客服全面上线</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── CTA ─── -->
    <section class="py-16 bg-gradient-to-r from-primary-600 to-blue-700">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">加入互物通，开启高效授权管理</h2>
            <p class="text-primary-100 mb-8">免费注册，一分钟完成集成。无需信用卡，无隐藏费用。</p>
            <a href="/build/register" class="inline-block bg-white text-primary-600 px-8 py-3 rounded-xl font-bold hover:bg-primary-50 transition shadow-lg">免费开始使用 →</a>
        </div>
    </section>

    @include('public.partials.footer')
</body>
</html>
