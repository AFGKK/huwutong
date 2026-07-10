<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ site_setting('site_description') }}">
    <meta name="keywords" content="{{ site_setting('site_keywords') }}">
    <meta property="og:title" content="{{ site_setting('site_name') }} - 企业级授权管理">
    <meta property="og:description" content="为您的软件产品提供安全、灵活、可扩展的授权解决方案">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('public.partials.tracking')

    <title>互物通 | 企业级授权管理系统</title>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "互物通",
        "description": "企业级授权管理系统，为软件产品提供从激活验证到商业运营的一站式授权解决方案。支持Ed25519 签名、离线验证、多平台 SDK",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Linux, Windows, macOS",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "CNY"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "互物通"",
        "url": "{{ url('/') }}",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "{{ url('/products?search={search_term_string}') }}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "互物通",
        "url": "{{ url('/') }}",
        "description": "企业级授权管理系统,
        "foundingDate": "2024",
        "founder": { "@type": "Person", "name": "互物通团队 },
        "address": { "@type": "PostalAddress", "addressCountry": "CN" }
    }
    </script>

    @vite('resources/css/public.css')
    <style>
        html { scroll-behavior: smooth; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(59,130,246,0.15); }
        .product-card { transition: all 0.3s ease; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(59,130,246,0.15); }
        .aspect-square { aspect-ratio: 1 / 1 !important; }
        .plan-card { transition: all 0.3s ease; }
        .plan-card.popular { border-color: #3b82f6; transform: scale(1.02); }
        .plan-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15); }
        .plan-card.popular:hover { transform: scale(1.02) translateY(-6px); }
        /* Logo 墙自动轮播*/
        .logo-track { overflow: hidden; width: 100%; }
        .logo-slide { display: flex; gap: 24px; width: max-content; animation: scrollLeft 30s linear infinite; }
        .logo-track-reverse .logo-slide { animation: scrollRight 30s linear infinite; }
        .logo-item { flex-shrink: 0; width: 140px; }
        .logo-placeholder { width: 140px; height: 72px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; transition: transform 0.3s; }
        .logo-placeholder:hover { transform: scale(1.08); }
</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')

    <!-- ─── Hero 区域 ─── -->
    <section class="relative pt-28 pb-20 md:pt-36 md:pb-28 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-600 via-primary-700 to-blue-900"></div>
        <!-- 装饰在-->
        <div class="absolute top-1/4 -left-32 w-96 h-96 bg-primary-200 rounded-full blur-[100px] opacity-40 animate-pulse"></div>
        <div class="absolute top-1/3 right-0 w-80 h-80 bg-blue-200 rounded-full blur-[100px] opacity-30"></div>
        <div class="absolute bottom-1/4 left-1/3 w-64 h-64 bg-purple-200 rounded-full blur-[100px] opacity-20"></div>
        <!-- 网格背景 -->
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle at 1px 1px, #3b82f6 1px, transparent 0); background-size: 40px 40px;"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center gap-2 bg-gradient-to-r from-primary-50 to-blue-50 text-primary-700 px-5 py-2 rounded-full text-sm font-medium mb-8 border border-primary-200/50 shadow-sm animate-fade-in">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                <span>v2.0 全新发布</span>
                <span class="w-1 h-1 bg-primary-300 rounded-full"></span>
                <span>离线验证 & Edge 边缘授权</span>
            </div>
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-gray-900 leading-tight mb-6 animate-fade-in" style="animation-delay:0.1s">
                企业级授权管理<br>
                <span class="bg-gradient-to-r from-primary-500 via-blue-500 to-primary-500 bg-[length:200%_auto] animate-gradient bg-clip-text text-transparent">安全 · 灵活 · 可扩展</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto mb-10 leading-relaxed animate-fade-in" style="animation-delay:0.2s">
                为您的软件产品提供从激活验证到商业运营的一站式授权解决方案　<br>
                Ed25519 签名 · 离线验证 · 多平台SDK · 99.99% SLA
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in" style="animation-delay:0.3s">
                <a href="/build/register" class="inline-flex items-center gap-2 bg-white text-primary-700 px-8 py-3.5 rounded-xl font-bold hover:bg-primary-50 transition-all shadow-lg hover:shadow-xl">
                    免费开始<span class="inline-block group-hover:translate-x-1 transition-transform">→</span>
                </a>
                <a href="/pricing" class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-white px-8 py-3.5 rounded-xl font-medium hover:bg-white/20 transition-all border border-white/20">
                    查看定价
                </a>
                <a href="#features" class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-white px-8 py-3.5 rounded-xl font-medium hover:bg-white/20 transition-all border border-white/20">
                    了解更多
                </a>
            </div>
            <!-- 统计数据 -->
            <div class="mt-20 grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto">
                <div class="group">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent group-hover:scale-110 transition-transform duration-300">10,000+</div>
                    <div class="text-sm text-gray-500 mt-1.5">活跃客户</div>
                </div>
                <div class="group">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent group-hover:scale-110 transition-transform duration-300">500万</div>
                    <div class="text-sm text-gray-500 mt-1.5">License 生成</div>
                </div>
                <div class="group">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent group-hover:scale-110 transition-transform duration-300">99.99%</div>
                    <div class="text-sm text-gray-500 mt-1.5">服务可用性</div>
                </div>
                <div class="group">
                    <div class="text-3xl md:text-4xl font-bold bg-gradient-to-b from-gray-900 to-gray-600 bg-clip-text text-transparent group-hover:scale-110 transition-transform duration-300">&lt;10ms</div>
                    <div class="text-sm text-gray-500 mt-1.5">平均验证延迟</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 客户 Logo 墙 ─── -->
    <section class="py-16 bg-gray-50 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Trusted by 500+</p>
            <p class="text-lg text-gray-400 mb-10">受到全球 10,000+ 企业的信赖</p>

            <!-- 第一行（向左滚动）-->
            <div class="logo-track mb-8">
                <div class="logo-slide">
                    @for($i = 0; $i < 2; $i++)
                    <div class="logo-item"><div class="logo-placeholder" style="background:#e8f4f8"><span class="logo-<br>TechCorp</span><span class="logo-industry">科技</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f0e6ff"><span class="logo-<br>DataFlow</span><span class="logo-industry">数据</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#e6f7ee"><span class="logo-<br>CloudBase</span><span class="logo-industry">云服务</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#fff4e6"><span class="logo-<br>SoftWare</span><span class="logo-industry">软件</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f0f0ff"><span class="logo-<br>AIStudio</span><span class="logo-industry">AI</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#fff0f0"><span class="logo-<br>NetCore</span><span class="logo-industry">网络</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f5f0e6"><span class="logo-<br>SmartDev</span><span class="logo-industry">IoT</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#e6f0ff"><span class="logo-<br>CloudSync</span><span class="logo-industry">同步</span></div></div>
                    @endfor
                </div>
            </div>

            <!-- 第二行（向右滚动）-->
            <div class="logo-track logo-track-reverse">
                <div class="logo-slide">
                    @for($i = 0; $i < 2; $i++)
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f5e6e6"><span class="logo-<br>FinTech</span><span class="logo-industry">金融</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#e6f5e6"><span class="logo-<br>MediCore</span><span class="logo-industry">医疗</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f0e6f0"><span class="logo-<br>EduTech</span><span class="logo-industry">教育</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#fffde6"><span class="logo-<br>GameFun</span><span class="logo-industry">游戏</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#e6f0f5"><span class="logo-<br>SafeGuard</span><span class="logo-industry">安全</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f5f0e8"><span class="logo-<br>RoboWork</span><span class="logo-industry">制造</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#f0fff0"><span class="logo-<br>GreenEng</span><span class="logo-industry">能源</span></div></div>
                    <div class="logo-item"><div class="logo-placeholder" style="background:#fff0f5"><span class="logo-<br>LogiX</span><span class="logo-industry">物流</span></div></div>
                    @endfor
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 产品特性 ─── -->
    <section id="features" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 animate-slide-up">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">为什么选择互物通？</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">从独立开发者到跨国企业，我们为各种规模的软件团队提供完整的授权管理基础设施</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="feature-card p-8 rounded-2xl border border-gray-100 bg-white transition-all">
                    <div class="w-12 h-12 bg-primary-50 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">安全可靠</h3>
                    <p class="text-gray-600 leading-relaxed">Ed25519 签名算法 + 离线验证 + CRL 吊销列表，银行级安全保障</p>
                </div>
                <div class="feature-card p-8 rounded-2xl border border-gray-100 bg-white transition-all">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">高性能</h3>
                    <p class="text-gray-600 leading-relaxed">单机 5000+ QPS，边缘节点&lt;10ms 验证延迟，全球CDN 加速</p>
                </div>
                <div class="feature-card p-8 rounded-2xl border border-gray-100 bg-white transition-all">
                    <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">全平台覆盖</h3>
                    <p class="text-gray-600 leading-relaxed">PHP/Node.js/Python/Go/Java/C# SDK，桌面移动/嵌入式全支持</p>
                </div>
                <div class="feature-card p-8 rounded-2xl border border-gray-100 bg-white transition-all">
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">灵活部署</h3>
                    <p class="text-gray-600 leading-relaxed">SaaS 云服务+ 私有化部署+ 完全离线气隙模式，按需选择</p>
                </div>
                <div class="feature-card p-8 rounded-2xl border border-gray-100 bg-white transition-all">
                    <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">数据驱动</h3>
                    <p class="text-gray-600 leading-relaxed">实时分析看板 + AI 运营分析 + 自动报表，洞察业务增长</p>
                </div>
                <div class="feature-card p-8 rounded-2xl border border-gray-100 bg-white transition-all">
                    <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">全球合规</h3>
                    <p class="text-gray-600 leading-relaxed">GDPR/PIPL/SOC2/ISO27001 合规，满足全球各地法律法规</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 开放平台 & 应用市场 ─── -->
    <section class="py-20 md:py-28 bg-gradient-to-br from-gray-50 to-primary-50/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">开放平台 &amp; 应用市场</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">构建开发者生态，让第三方应用与您的产品无缝集成</p>
            </div>
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="space-y-8">
                    <div class="flex gap-5">
                        <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">开发者注册</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">第三方开发者注册成为平台开发者，提交应用审核，上架销售</p>
                        </div>
                    </div>
                    <div class="flex gap-5">
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">应用发布 &amp; 审核</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">完整的上架流程：提交→审核→发布，支持灰度发布和版本管理</p>
                        </div>
                    </div>
                    <div class="flex gap-5">
                        <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">收入结算 &amp; 提现</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">付费应用收入自动结算，平台抽成透明，支持多种提现渠道</p>
                        </div>
                    </div>
                    <div class="flex gap-5">
                        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">内容安全 &amp; 风控</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">自动敏感词检测 + 违规下架 + 强制通知，保障平台生态安全</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">加入开放平台</h3>
                        <p class="text-gray-500 text-sm mt-2">发布您的应用，触达数千企业客户</p>
                    </div>
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3 text-sm text-gray-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>免费注册成为开发者</div>
                        <div class="flex items-center gap-3 text-sm text-gray-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>应用上架审核快速通道</div>
                        <div class="flex items-center gap-3 text-sm text-gray-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>透明收益结算与提现</div>
                        <div class="flex items-center gap-3 text-sm text-gray-600"><svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>开发者工单与技术支持</div>
                    </div>
                    <a href="/build/open-platform" class="block text-center bg-gradient-to-r from-primary-500 to-primary-600 text-white px-6 py-3 rounded-xl font-medium hover:from-primary-600 hover:to-primary-700 transition-all shadow-md hover:shadow-lg">进入开放平台 →</a>
                    <p class="text-center text-xs text-gray-400 mt-3">已有应用？<a href="/build/app-marketplace" class="text-primary-500 hover:text-primary-600">浏览应用市场 →</a></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 工作原理 ─── -->
    <section id="how-it-works" class="py-20 md:py-28 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">如何工作</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">三步完成授权集成，一分钟即可上线</p>
            </div>
            <div class="grid md:grid-cols-3 gap-12">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <span class="text-2xl font-bold text-primary-600">1</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">注册 & 创建产品</h3>
                    <p class="text-gray-600 leading-relaxed">免费注册账户，在后台创建您的软件产品和定价方案</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <span class="text-2xl font-bold text-primary-600">2</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">集成 SDK</h3>
                    <p class="text-gray-600 leading-relaxed">选择您的编程语言，复制3 行代码即可完成授权验证集成</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <span class="text-2xl font-bold text-primary-600">3</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">生成 & 分发 License</h3>
                    <p class="text-gray-600 leading-relaxed">批量生成 License Key，分发给您的客户，全程自动化管理</p>
                </div>
            </div>
            <!-- Integration code preview -->
            <div class="mt-16 bg-gray-900 rounded-2xl p-8 max-w-3xl mx-auto shadow-xl">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                    <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                    <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                    <span class="text-gray-400 text-sm ml-2">一分钟集成示例</span>
                </div>
@verbatim
                <pre class="text-sm text-gray-200 font-mono leading-relaxed overflow-x-auto"><code>// composer require huwutong/sdk
<span class="text-blue-400">$client</span> = <span class="text-purple-400">new</span> <span class="text-green-400">HWTClient</span>(<span class="text-orange-400">'your_api_key'</span>);
<span class="text-blue-400">$result</span> = <span class="text-blue-400">$client</span>-><span class="text-yellow-300">validate</span>(<span class="text-orange-400">'HWT-ENT-XXXX-XXXX'</span>);

<span class="text-gray-400">if</span> (<span class="text-blue-400">$result</span>-><span class="text-yellow-300">isValid</span>()) {
    <span class="text-gray-400">echo</span> <span class="text-green-400">"✀License 有效，剩余{{ $result->daysRemaining }} 天"</span>;
}</code></pre>
@endverbatim
            </div>
        </div>
    </section>

    <!-- ─── 精选产品─── -->
    <section id="featured-products" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">精选产品</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">平台上热门的软件产品，为您的业务提供强大支持</p>
            </div>
            <div id="landing-products" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($featuredProducts as $product)
                    <div class="product-card bg-white rounded-xl border border-gray-100 overflow-hidden flex flex-col group relative">
                        <a href="{{ url('/products/'.$product->slug) }}" class="block flex flex-col flex-1">
                        <div class="aspect-square bg-gradient-to-br from-primary-50 to-blue-50">
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                            @else
                                <div class="text-center p-6">
                                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span class="text-sm text-gray-400">{{ $product->name }}</span>
                                </div>
                            @endif
                            <!-- 角标 -->
                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                @if($product->is_new ?? false)
                                    <span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">新品</span>
                                @endif
                                @if($product->is_hot ?? false)
                                    <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">热卖</span>
                                @endif
                                @if($product->has_discount ?? false)
                                    <span class="px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded-full">优惠</span>
                                @endif
                                @if($product->demo_enabled ?? false)
                                    <span class="px-2 py-0.5 bg-purple-500 text-white text-xs font-bold rounded-full">演示</span>
                                @endif
                            </div>
                            <!-- 收藏按钮 -->
                            <button type="button" onclick="toggleWishlist(event, {{ $product->id }})"
                                class="absolute top-3 right-3 w-8 h-8 bg-white/80 backdrop-blur-sm rounded-full flex items-center justify-center shadow-sm hover:bg-white hover:shadow-md transition-all z-10 wishlist-btn"
                                data-product-id="{{ $product->id }}"
                                title="收藏">
                                <svg class="w-4 h-4 text-gray-400 transition-colors wishlist-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="p-5 flex flex-col flex-1" style="min-height:220px">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 group-hover:text-primary-600 transition line-clamp-1">{{ $product->name }}</h3>
                                @if($product->version)
                                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0 ml-2">v{{ $product->version }}</span>
                                @endif
                            </div>
                            <!-- 评分 -->
                            @php $rs = $product->review_stats; @endphp
                            @if($rs['total'] > 0)
                                <div class="flex items-center gap-1 mb-1">
                                    <span class="text-yellow-400 text-sm">{{ str_repeat('★', min(5, max(0, round($rs['avg_rating'])))) }}{{ str_repeat('★', max(0, 5 - min(5, max(0, round($rs['avg_rating']))))) }}</span>
                                    <span class="text-xs text-gray-400">{{ number_format($rs['avg_rating'], 1) }}</span>
                                    <span class="text-xs text-gray-300">({{ $rs['total'] }})</span>
                                </div>
                            @endif
                            @if($product->category)
                                <span class="text-xs text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full inline-block w-fit mb-2">{{ $product->category->name }}</span>
                            @endif
                            <p class="text-sm text-gray-500 line-clamp-2 mb-3 flex-1">{{ $product->description ?: '暂无描述' }}</p>
                            @if($product->creator)
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-5 h-5 rounded-full overflow-hidden flex-shrink-0 bg-primary-50 flex items-center justify-center">
                                        @if($product->creator->avatar_url)
                                            <img src="{{ $product->creator->avatar_url }}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" loading="lazy">
                                        @endif
                                        <span class="text-primary-600 font-bold text-[10px]" @if($product->creator->avatar_url) style="display:none" @endif>{{ mb_substr($product->creator->name, 0, 1) }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ $product->creator->name }}</span>
                                </div>
                            @endif
                            <!-- 价格 + 销量 -->
                            <div class="flex items-end justify-between pt-2 border-t border-gray-50 mt-auto">
                                <div>
                                    @if($product->lowest_price)
                                        <span class="text-lg font-bold text-primary-600">¥{{ number_format($product->lowest_price, 2) }}</span>
                                        @if($product->highest_price && $product->highest_price > $product->lowest_price)
                                            <span class="text-xs text-gray-400"> - ¥{{ number_format($product->highest_price, 2) }}</span>
                                        @endif
                                        <span class="text-xs text-gray-400 ml-1">/月</span>
                                    @else
                                        <span class="text-sm text-gray-400">价格待定</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-400">{{ $product->sold_total ?? 0 }} 已售</span>
                                    <div class="text-xs text-gray-400">{{ $product->licenses_count ?? 0 }} License</div>
                                </div>
                            </div>
                        </div>
                        </a>
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border-2 border-dashed border-gray-200 p-6 flex items-center justify-center min-h-[200px]">
                        <p class="text-gray-400 text-sm text-center">更多产品即将上架<br><a href="/products" class="text-primary-600 hover:text-primary-700 font-medium">查看全部 →</a></p>
                    </div>
                @endforelse
            </div>
            <div class="text-center mt-10">
                <a href="/products" class="inline-flex items-center gap-2 bg-primary-600 text-white px-8 py-3.5 rounded-xl font-bold hover:bg-primary-700 transition-all shadow-lg">
                    浏览全部产品 →
                </a>
            </div>
        </div>
    </section>

    <!-- ─── 定价预览 ─── -->
    <section class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">透明定价</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">从免费版到企业版，满足不同规模团队的需求</p>
            </div>
            <div class="flex justify-center mb-8">
                <div id="landing-billing-toggle" class="inline-flex items-center bg-gray-100 rounded-full p-1">
                    <button onclick="switchLandingBilling('monthly')" id="landing-mo-btn" class="px-6 py-2 rounded-full font-medium transition bg-white shadow-sm text-gray-900">月度</button>
                    <button onclick="switchLandingBilling('yearly')" id="landing-yr-btn" class="px-6 py-2 rounded-full font-medium text-gray-500 hover:text-gray-900">年度<span class="ml-1 text-xs text-green-500 font-medium">省20%</span></button>
                </div>
            </div>
            <div id="landing-plans" class="grid md:grid-cols-3 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
                <!-- Plans will be loaded via JS -->
            </div>
            <div class="text-center mt-10">
                <a href="/pricing" class="inline-flex items-center gap-2 bg-primary-600 text-white px-8 py-3.5 rounded-xl font-bold hover:bg-primary-700 transition-all shadow-lg">
                    查看完整定价详情和功能对比→
                </a>
            </div>
        </div>
    </section>

    <!-- ─── FAQ ─── -->
    <section id="faq" class="py-20 md:py-28 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">常见问题</h2>
            </div>
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">互物通如何保护我的软件License 不被破解？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">
                        互物通采用Ed25519 签名算法对License Key 进行签名，私钥仅在服务端安全存储。同时支持离线验证文件加密签名、CRL 吊销列表实时更新、设备指纹绑定、蜜罐防御等多层防护机制。即使客户端被逆向，也无法伪造有效License
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">支持的编程语言和平台有哪些？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">
                        我们提供 PHP、Node.js、Python、Go、Java、C# 六种官方 SDK，覆盖Web 后端、桌面应用（Electron/Tauri）、移动端（Flutter）、嵌入式设备等多种场景
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">支持离线环境吗？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">
                        支持！互物通提供完整的离线验证方案：RSA/ECC 加密离线验证文件、30 天有效期、CRL 离线吊销列表、网络恢复自动补全验证。另外还支持完全气隙部署模式，适用于军工、政府、银行等内网环境
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">可以私有化部署吗？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">
                        可以。互物通支持Docker Compose / K8s 私有化部署，提供完整的Docker 镜像和Helm Chart。企业版客户还可选择完全离线的气隙部署模式，数据不出企业内网
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">如何迁移现有授权系统到互物通？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">
                        互物通提供AI 迁移助手，支持从 Cryptlex、Localazy、Keygen.sh、LicenseSpring 等主流授权平台一键导入。也支持自定义CSV 导入模板。迁移过程可预览、可回滚，确保业务不中断
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 社区精选 ─── -->
    <section class="py-16 md:py-20 bg-gray-50" id="community-posts">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">🌐 社区精选</h2>
                    <p class="text-gray-500 mt-2">来自用户的热门讨论与经验分享</p>
                </div>
                <a href="/build/community" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 transition">
                    查看更多 <span>→</span>
                </a>
            </div>
            <div id="community-feed" class="grid md:grid-cols-2 gap-4">
                <div class="text-center text-gray-400 py-8 col-span-full">加载中...</div>
            </div>
            <div class="text-center mt-6 sm:hidden">
                <a href="/build/community" class="inline-flex items-center gap-1 text-sm font-medium text-primary-600">查看更多 →</a>
            </div>
        </div>
    </section>

    <!-- ─── 推荐互物号 ─── -->
    <section class="py-16 md:py-20 bg-white" id="featured-channels">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">📢 推荐互物号</h2>
                    <p class="text-gray-500 mt-2">关注你感兴趣的互物号，获取最新动态</p>
                </div>
                <a href="/build/channels" class="hidden sm:inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 transition">
                    浏览全部 <span>→</span>
                </a>
            </div>
            <div id="channels-grid" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center text-gray-400 py-8 col-span-full">加载中...</div>
            </div>
            <div class="text-center mt-6 sm:hidden">
                <a href="/build/channels" class="inline-flex items-center gap-1 text-sm font-medium text-primary-600">浏览全部 →</a>
            </div>
        </div>
    </section>

    <script>
    // 加载社区精选帖子
    (async function() {
        try {
            var r = await fetch('/api/moments?per_page=4&sort=trending');
            var d = await r.json();
            var posts = d.data?.data || d.data || [];
            var container = document.getElementById('community-feed');
            if (!posts.length) { container.innerHTML = '<div class="text-center text-gray-300 py-8 col-span-full">暂无内容</div>'; return; }
            container.innerHTML = posts.map(function(p) {
                var name = p.user?.name || '匿名';
                var avatar = p.user?.avatar ? '<img src="'+p.user.avatar+'" class="w-full h-full object-cover" />' : '<span>'+name.charAt(0)+'</span>';
                var images = '';
                if (p.images) {
                    try { var imgs = typeof p.images === 'string' ? JSON.parse(p.images) : p.images;
                        images = imgs.slice(0,2).map(function(i) { return '<img src="'+i+'" class="w-full h-28 object-cover rounded-lg" />'; }).join('');
                    } catch(e) {}
                }
                var content = (p.content || '').substring(0, 120);
                if ((p.content || '').length > 120) content += '...';
                var tags = '';
                if (p.tags && p.tags.length) {
                    tags = p.tags.map(function(t) { return '<span class="px-2 py-0.5 bg-gray-50 text-gray-400 rounded-full text-xs">'+(t.name||t)+'</span>'; }).join('');
                }
                return '<div class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-md transition cursor-pointer" onclick="window.open(\'/build/plaza/'+p.id+'\',\'_blank\')">'+
                    '<div class="flex items-center gap-2 mb-3">'+
                        '<div class="w-7 h-7 rounded-full bg-primary-50 flex items-center justify-center text-primary-600 text-xs font-bold overflow-hidden">'+avatar+'</div>'+
                        '<span class="text-sm text-gray-500">'+name+'</span>'+
                    '</div>'+
                    '<p class="text-sm text-gray-700 leading-relaxed mb-3">'+content+'</p>'+
                    (images ? '<div class="flex gap-2 mb-3">'+images+'</div>' : '')+
                    '<div class="flex items-center gap-3 text-xs text-gray-400">'+
                        '<span>❤️ '+(p.likes_count||0)+'</span>'+
                        '<span>💬 '+(p.replies_count||0)+'</span>'+
                        (tags ? '<span class="ml-auto flex gap-1">'+tags+'</span>' : '')+
                    '</div>'+
                '</div>';
            }).join('');
        } catch(e) { document.getElementById('community-feed').innerHTML = '<div class="text-center text-gray-300 py-8 col-span-full">加载失败</div>'; }
    })();

    // 加载推荐互物号
    (async function() {
        try {
            var r = await fetch('/api/official-accounts?per_page=4&sort=followers');
            var d = await r.json();
            var accounts = d.data?.data || d.data || [];
            var container = document.getElementById('channels-grid');
            if (!accounts.length) { container.innerHTML = '<div class="text-center text-gray-300 py-8 col-span-full">暂无内容</div>'; return; }
            container.innerHTML = accounts.map(function(a) {
                var initial = (a.name||'号').charAt(0);
                var avatar = a.avatar ? '<img src="'+a.avatar+'" class="w-full h-full object-cover" />' : '<span class="text-lg font-bold">'+initial+'</span>';
                return '<div class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-md transition cursor-pointer" onclick="window.open(\'/build/channels\',\'_blank\')">'+
                    '<div class="flex items-center gap-3 mb-3">'+
                        '<div class="w-11 h-11 rounded-xl bg-primary-50 flex items-center justify-center text-primary-600 overflow-hidden">'+avatar+'</div>'+
                        '<div class="flex-1 min-w-0">'+
                            '<div class="text-sm font-semibold text-gray-900 truncate">'+(a.name||'互物号')+'</div>'+
                            '<div class="text-xs text-gray-400">'+(a.followers_count||0)+' 关注者</div>'+
                        '</div>'+
                    '</div>'+
                    '<p class="text-xs text-gray-500 line-clamp-2">'+(a.description||'暂无简介')+'</p>'+
                '</div>';
            }).join('');
        } catch(e) { document.getElementById('channels-grid').innerHTML = '<div class="text-center text-gray-300 py-8 col-span-full">加载失败</div>'; }
    })();
    </script>

    <!-- ─── CTA ─── -->
    <section class="py-20 bg-gradient-to-r from-primary-600 to-blue-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">准备好保护您的软件了吗？</h2>
            <p class="text-lg text-primary-100 mb-8 max-w-2xl mx-auto">免费注册，一分钟完成集成。无需信用卡，无隐藏费用。</p>
            <a href="/build/register" class="inline-flex items-center gap-2 bg-white text-primary-700 px-10 py-4 rounded-xl font-bold text-lg hover:bg-primary-50 transition-all shadow-xl hover:shadow-2xl">
                免费开始使用<span class="ml-2">→</span>
            </a>
            <p class="text-primary-200 text-sm mt-4">14 天免费试用· 随时取消</p>
        </div>
    </section>

    <!-- ─── Footer ─── -->
    @include('public.partials.footer')

    <script>

    <script>
    // FAQ toggle
    function toggleFaq(btn) {
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('svg');
        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }

    // Load pricing plans
    fetch('/api/public/pricing-plans')
        .then(r => r.json())
        .then(data => {
            const plans = data.data;
            const container = document.getElementById('landing-plans');
            if (!container) return;
            container.innerHTML = plans.map(plan => `
                <div class="plan-card rounded-xl border-2 ${plan.badge === 'popular' ? 'border-primary-500 popular' : 'border-gray-100'} bg-white p-6 flex flex-col">
                    ${plan.badge === 'popular' ? '<div class="text-xs font-semibold text-primary-600 bg-primary-50 rounded-full px-3 py-1 mb-3 inline-block self-start">最受欢迎</div>' : ''}
                    ${plan.badge === 'best_value' ? '<div class="text-xs font-semibold text-amber-600 bg-amber-50 rounded-full px-3 py-1 mb-3 inline-block self-start">最佳性价比</div>' : ''}
                    <h3 class="text-lg font-bold text-gray-900">${plan.name}</h3>
                    <p class="text-sm text-gray-500 mt-1 mb-4">${plan.description}</p>
                    <div class="mb-4">
                        <span class="text-3xl font-bold text-gray-900">¥${plan.price_monthly}</span>
                        <span class="text-gray-500 text-sm">/月</span>
                    </div>
                    <ul class="space-y-2 text-sm text-gray-600 flex-1 mb-6">
                        ${plan.features.map(f => `<li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>${f}</li>`).join('')}
                    </ul>
                    <a href="/build/subscribe/${plan.id}" class="block w-full text-center py-3 rounded-xl font-semibold transition ${plan.price_monthly === 0 ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-primary-600 text-white hover:bg-primary-700 shadow-lg'} text-sm">${plan.price_monthly === 0 ? '免费开始' : '立即订阅'}</a>
                </div>
            `).join('');
        })
        .catch(() => {});

    // Billing toggle
    function switchLandingBilling(period) {
        const moBtn = document.getElementById('landing-mo-btn');
        const yrBtn = document.getElementById('landing-yr-btn');
        moBtn.classList.toggle('bg-white', period === 'monthly');
        moBtn.classList.toggle('shadow-sm', period === 'monthly');
        moBtn.classList.toggle('text-gray-900', period === 'monthly');
        moBtn.classList.toggle('text-gray-500', period !== 'monthly');
        yrBtn.classList.toggle('bg-white', period === 'yearly');
        yrBtn.classList.toggle('shadow-sm', period === 'yearly');
        yrBtn.classList.toggle('text-gray-900', period === 'yearly');
        yrBtn.classList.toggle('text-gray-500', period !== 'yearly');
    }

    // ─── 收藏夹功能───
    const wishlistedIds = new Set();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // 加载已收藏的产品 ID
    function loadWishlist() {
        fetch('/api/wishlist/my/product-ids', {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            (data.data?.product_ids || []).forEach(id => wishlistedIds.add(id));
            document.querySelectorAll('.wishlist-btn').forEach(btn => {
                const id = parseInt(btn.dataset.productId);
                if (wishlistedIds.has(id)) setWishlisted(btn, true);
            });
        })
        .catch(() => {});
    }

    // 设置收藏图标状态
    function setWishlisted(btn, state) {
        const icon = btn.querySelector('.wishlist-icon');
        if (state) {
            icon.classList.add('text-red-500', 'fill-current');
            icon.classList.remove('text-gray-400');
            btn.classList.add('opacity-100');
        } else {
            icon.classList.remove('text-red-500', 'fill-current');
            icon.classList.add('text-gray-400');
        }
    }

    // 切换收藏
    function toggleWishlist(e, productId) {
        e.preventDefault();
        e.stopPropagation();

        const btn = e.currentTarget;

        fetch('/api/wishlist/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'include',
            body: JSON.stringify({ product_id: productId }),
        })
        .then(r => {
            if (r.status === 401) {
                window.location.href = '/build/login';
                return;
            }
            return r.json();
        })
        .then(data => {
            if (!data) return;
            const nowWishlisted = wishlistedIds.has(productId);
            if (nowWishlisted) {
                wishlistedIds.delete(productId);
                setWishlisted(btn, false);
            } else {
                wishlistedIds.add(productId);
                setWishlisted(btn, true);
                // 小动画反馈
                btn.classList.add('animate-bounce');
                setTimeout(() => btn.classList.remove('animate-bounce'), 300);
            }
        })
        .catch(() => {});
    }

    // 页面加载后加载收藏状态
    document.addEventListener('DOMContentLoaded', loadWishlist);
    </script>
</body>
</html>
