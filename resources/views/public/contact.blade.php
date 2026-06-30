<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>联系我们 - 互物通 | 企业级授权管理系统</title>
<meta name="description" content="有任何问题？联系互物通团队，我们将在1个工作日内回复">
<meta property="og:title" content="联系我们 - 互物通 | 企业级授权管理">
<meta property="og:description" content="有任何问题？联系互物通团队，我们将在1个工作日内回复">
<meta property="og:type" content="website">
<link rel="canonical" href="{{ url('/contact') }}">
@include('public.partials.tracking')
@vite('resources/css/public.css')
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
                <span class="text-gray-700 font-medium">联系我们</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">联系我们</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">我们很乐意听到您的声音</p>
            </div>
        </div>
    </section>
    <section class="py-16">
        <div class="max-w-4xl mx-auto px-4 grid md:grid-cols-2 gap-12">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-6">联系方式</h2>
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-primary-50 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                        <div><h3 class="font-semibold text-gray-900">邮件</h3><p class="text-gray-500">{{ site_setting('contact_email', 'support@huwutong.com') }}</p></div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-primary-50 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                        <div><h3 class="font-semibold text-gray-900">地址</h3><p class="text-gray-500">{{ site_setting('contact_address', '上海市浦东新区张江高科技园区') }}</p></div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-primary-50 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <div><h3 class="font-semibold text-gray-900">工作时间</h3><p class="text-gray-500">{{ site_setting('working_hours', '周一至周五 9:00 - 18:00') }}</p></div>
                    </div>
                </div>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-6">联系我们 / 预约演示</h2>
                <div id="contact-success" class="hidden bg-green-50 border border-green-200 rounded-xl p-6 text-center mb-6">
                    <div class="text-4xl mb-3"></div>
                    <h3 class="text-lg font-bold text-green-800 mb-1">感谢您的留言！</h3>
                    <p class="text-green-600 text-sm">我们的销售团队将在4小时内与您联系，确认邮件已发送到您的邮箱。</p>
                </div>
                <form id="contact-form" onsubmit="return submitContact(event)" class="space-y-4">
                    <input type="text" name="website_url" class="hidden" tabindex="-1" autocomplete="off">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">公司名称 *</label><input type="text" id="field-company" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">联系人 *</label><input type="text" id="field-name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">邮箱 *</label><input type="email" id="field-email" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">手机号</label><input type="tel" id="field-phone" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">员工规模</label>
                            <select id="field-employees" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                                <option value="">请选择</option>
                                <option>1-10 人</option><option>11-50 人</option><option>51-200 人</option>
                                <option>201-1000 人</option><option>1000+ 人</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">感兴趣的产品</label>
                            <select id="field-interest" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                                <option value="">请选择</option>
                                <option>License授权管理</option><option>设备管理</option>
                                <option>API/SDK集成</option><option>安全风控</option><option>企业版全套</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">备注信息</label><textarea id="field-message" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm" placeholder="请描述您的需求，例如：需要管理0000+终端设备、需要OEM白标方案..."></textarea></div>
                    <button type="submit" id="contact-submit-btn" class="w-full bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition text-sm">
                        提交预约
                    </button>
                    <p id="contact-msg" class="text-sm hidden text-center"></p>
                </form>
            </div>
        </div>
    </section>

    <!-- ─── 企业优势 ─── -->
    <section class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">为什么选择互物通？</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-14 h-14 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">企业级安全</h3>
                    <p class="text-sm text-gray-500">Ed25519 签名、设备指纹、AI 风控，军工级安全保障</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">极速集成</h3>
                    <p class="text-sm text-gray-500">5 分钟完成 SDK 集成，支持PHP/Node/Python/Java 等多语言</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">全球合规</h3>
                    <p class="text-sm text-gray-500">GDPR/PIPL/SOC2/ISO27001 合规，服务全球客户</p>
                </div>
            </div>
        </div>
    </section>

    <script>
    async function submitContact(e) {
        e.preventDefault();
        var btn = document.getElementById('contact-submit-btn');
        var msg = document.getElementById('contact-msg');
        btn.disabled = true;
        btn.textContent = '提交中...';
        msg.classList.add('hidden');

        var data = {
            company: document.getElementById('field-company')?.value || '',
            name: document.getElementById('field-name')?.value || '',
            email: document.getElementById('field-email')?.value || '',
            phone: document.getElementById('field-phone')?.value || '',
            employees: document.getElementById('field-employees')?.value || '',
            interest: document.getElementById('field-interest')?.value || '',
            message: document.getElementById('field-message')?.value || '',
        };

        try {
            var r = await fetch('/api/public/contact', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(data),
            });
            var res = await r.json();
            if (res.success) {
                document.getElementById('contact-success').classList.remove('hidden');
                document.getElementById('contact-form').classList.add('hidden');
            } else {
                msg.textContent = res.message || '提交失败，请稍后重试';
                msg.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = '提交预约';
            }
        } catch(e) {
            msg.textContent = '提交失败，请稍后重试或发送邮件至 support@huwutong.com';
            msg.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = '提交预约';
        }
    }
    </script>

    @include('public.partials.footer')
</body>
</html>
