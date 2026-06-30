<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="查询您的 License 授权状态 - 互物通">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="授权查询 | {{ site_setting('site_name', '互物通') }}">
    <meta property="og:description" content="输入 License Key 查询授权状态与详细信息">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/license/query') }}">
    @include('public.partials.tracking')
    <title>授权查询 | {{ site_setting('site_name', '互物通') }}</title>
    @vite('resources/css/public.css')
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        function doSearch() {
            const input = document.getElementById('licenseKey');
            const key = input ? input.value.trim() : '';
            if (!key) return;

            document.getElementById('loading')?.classList.remove('hidden');
            document.getElementById('error')?.classList.add('hidden');
            document.getElementById('result')?.classList.add('hidden');
            document.getElementById('notfound')?.classList.add('hidden');

            fetch('/api/license/public-lookup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ license_key: key })
            })
            .then(r => r.json())
            .then(res => {
                document.getElementById('loading')?.classList.add('hidden');

                if (!res.success || !res.found) {
                    document.getElementById('notfound')?.classList.remove('hidden');
                    return;
                }

                const d = res.data;
                document.getElementById('resultKey').textContent = d.license_key;
                document.getElementById('resultProduct').textContent = d.product_name;
                document.getElementById('resultType').textContent = d.license_type_label;
                document.getElementById('resultCreated').textContent = d.created_at || '-';
                document.getElementById('resultExpires').textContent = d.expires_at || '永久有效';
                document.getElementById('resultActivated').textContent = d.activated ? '已激活' : '未激活';
                document.getElementById('resultDevices').textContent = (d.activated_devices || 0) + ' / ' + (d.max_devices || 0) + ' 台';

                const badge = document.getElementById('statusBadge');
                const statusMap = {
                    'active': { label: '✅ 有效', class: 'bg-green-50 text-green-700' },
                    'expired': { label: '⏰ 已过期', class: 'bg-red-50 text-red-700' },
                    'suspended': { label: '⛔ 已暂停', class: 'bg-orange-50 text-orange-700' },
                    'revoked': { label: '🚫 已吊销', class: 'bg-gray-100 text-gray-600' },
                    'pending': { label: '⏳ 待激活', class: 'bg-blue-50 text-blue-700' },
                };
                const s = d.is_expired ? 'expired' : (statusMap[d.status] ? d.status : 'active');
                const info = statusMap[s] || { label: d.status_label || d.status, class: 'bg-gray-50 text-gray-700' };
                badge.textContent = info.label;
                badge.className = 'px-4 py-1.5 rounded-full text-sm font-medium ' + info.class;

                if (d.product_description) {
                    document.getElementById('resultDescText').textContent = d.product_description;
                    document.getElementById('resultDescription').classList.remove('hidden');
                } else {
                    document.getElementById('resultDescription').classList.add('hidden');
                }

                document.getElementById('result').classList.remove('hidden');
            })
            .catch(() => {
                document.getElementById('loading')?.classList.add('hidden');
                document.getElementById('errorMessage').textContent = '网络错误，请稍后重试';
                document.getElementById('error').classList.remove('hidden');
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('licenseKey');
            if (input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') doSearch();
                });
            }
        });
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    @include('public.partials.nav')

    <main class="flex-1 pt-24 pb-16">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- 面包屑 -->
            <nav class="flex items-center gap-1.5 text-sm mb-8" style="color:rgba(107,114,128,0.8)">
                <a href="{{ url('/') }}" class="hover:text-primary-600 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    首页
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-700 font-medium">授权查询</span>
            </nav>
            <!-- 页面标题 -->
            <div class="text-center mb-10 animate-fade-in">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 rounded-2xl mb-4">
                    <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">授权查询</h1>
                <p class="text-lg text-gray-500">输入 License Key 查询授权状态与详细信息</p>
            </div>

            <!-- 搜索卡片 - 宽屏 -->
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-10 mb-4 animate-slide-up">
                    <div class="flex flex-row gap-4">
                        <div class="flex-1">
                            <label for="licenseKey" class="sr-only">License Key</label>
                            <input
                                id="licenseKey"
                                type="text"
                                class="w-full px-6 py-4 border border-gray-200 rounded-xl text-base focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition placeholder-gray-400 bg-gray-50/50"
                                placeholder="请输入 License Key，例如：HWT-XXXX-XXXX-XXXX"
                                autocomplete="off"
                                />
                        </div>
                        <button
                            class="inline-flex items-center gap-2 px-10 py-4 text-white font-medium rounded-xl transition-all duration-300 shadow-md hover:shadow-lg active:scale-95 whitespace-nowrap text-base"
                            style="background:linear-gradient(135deg,#3b82f6,#2563eb)"
                            onclick="doSearch()">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            查询
                        </button>
                    </div>
                </div>
                <p class="text-sm text-gray-400 text-center">输入您收到的 License Key 即可查询授权状态，不涉及任何敏感信息</p>
            </div>

            <!-- 加载中 -->
            <div id="loading" class="hidden text-center py-12">
                <div class="inline-block w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                <p class="text-gray-500 mt-3">正在查询...</p>
            </div>

            <!-- 错误提示 -->
            <div id="error" class="hidden bg-red-50 border border-red-100 rounded-2xl p-6 mb-6 animate-fade-in">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-red-800">查询失败</h3>
                        <p id="errorMessage" class="text-red-600 text-sm mt-1"></p>
                    </div>
                </div>
            </div>

            <!-- 查询结果 -->
            <div id="result" class="hidden animate-fade-in">
                <!-- 状态卡片 -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                    <div class="p-6 md:p-8">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">授权信息</h2>
                                <p id="resultKey" class="text-sm text-gray-400 font-mono mt-1"></p>
                            </div>
                            <div id="statusBadge" class="px-4 py-1.5 rounded-full text-sm font-medium"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">产品名称</label>
                                    <p id="resultProduct" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">授权类型</label>
                                    <p id="resultType" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">创建时间</label>
                                    <p id="resultCreated" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">到期时间</label>
                                    <p id="resultExpires" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">激活状态</label>
                                    <p id="resultActivated" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">设备数量</label>
                                    <p id="resultDevices" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 产品描述 -->
                <div id="resultDescription" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hidden">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-2">产品描述</h3>
                    <p id="resultDescText" class="text-gray-600 text-sm leading-relaxed"></p>
                </div>
            </div>

            <!-- 未找到 -->
            <div id="notfound" class="hidden bg-amber-50 border border-amber-100 rounded-2xl p-6 text-center animate-fade-in">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-amber-800 mb-1">未找到该 License</h3>
                <p class="text-amber-600 text-sm">请检查输入的 License Key 是否正确，或联系我们的客服获取帮助</p>
                <div class="mt-4 flex items-center justify-center gap-3">
                    <a href="/contact" class="text-sm font-medium text-amber-700 hover:text-amber-800 underline">联系我们 →</a>
                    <a href="/help" class="text-sm font-medium text-amber-700 hover:text-amber-800 underline">帮助中心 →</a>
                </div>
            </div>
        </div>
    </main>

    @include('public.partials.footer')

</body>
</html>
