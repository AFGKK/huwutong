<!-- ─── Cookie 横幅 ─── -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 shadow-lg px-4 py-4 sm:px-6 sm:py-4 hidden" style="box-shadow: 0 -4px 24px rgba(0,0,0,0.08);">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
        <button onclick="rejectCookieConsent()" class="absolute top-2 right-2 sm:static sm:order-last w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 transition flex-shrink-0" title="关闭">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="flex items-start gap-3 flex-1">
            <span class="text-xl flex-shrink-0 mt-0.5">🍪</span>
            <div>
                <p class="text-sm text-gray-700 font-medium">Cookie 设置</p>
                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                    我们使用必要的 Cookie 来确保服务正常运行。您可以选择接受或拒绝非必要的 Cookie。
                    <a href="{{ url('/cookie-policy') }}" class="text-primary-600 hover:text-primary-700 underline ml-1">Cookie 政策</a> | <a href="{{ url('/privacy') }}" class="text-primary-600 hover:text-primary-700 underline ml-1">隐私政策</a>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 w-full sm:w-auto">
            <button onclick="acceptCookieConsent()" class="flex-1 sm:flex-none px-5 py-2 bg-gradient-to-r from-primary-600 to-blue-600 text-white text-sm font-medium rounded-lg hover:from-primary-700 hover:to-blue-700 transition shadow-sm">
                接受全部
            </button>
            <button onclick="rejectCookieConsent()" class="flex-1 sm:flex-none px-4 py-2 text-sm text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                拒绝
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var key = 'cookie_consent';
    var val = localStorage.getItem(key);
    if (!val) {
        // 检查 Vue 组件使用的 cookie_consent_given
        var vueConsent = localStorage.getItem('cookie_consent_given');
        if (!vueConsent) {
            var banner = document.getElementById('cookie-banner');
            if (banner) banner.classList.remove('hidden');
        }
    }
})();

function acceptCookieConsent() {
    var banner = document.getElementById('cookie-banner');
    if (banner) banner.classList.add('hidden');
    // 兼容 Vue CookieConsent 组件
    var data = JSON.stringify({ action: 'accepted', timestamp: Date.now(), categories: [] });
    localStorage.setItem('cookie_consent', 'accepted');
    localStorage.setItem('cookie_consent_given', data);
}

function rejectCookieConsent() {
    var banner = document.getElementById('cookie-banner');
    if (banner) banner.classList.add('hidden');
    var data = JSON.stringify({ action: 'rejected', timestamp: Date.now(), categories: [] });
    localStorage.setItem('cookie_consent', 'rejected');
    localStorage.setItem('cookie_consent_given', data);
}
</script>
