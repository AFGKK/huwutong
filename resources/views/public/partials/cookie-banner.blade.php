<!-- ─── Cookie consent banner ─── -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 shadow-lg hidden" style="box-shadow: 0 -4px 24px rgba(0,0,0,0.08);">
    <div class="max-w-6xl mx-auto px-4 py-4 sm:px-6 sm:py-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
            <div class="flex items-start gap-3 flex-1">
                <span class="text-xl flex-shrink-0 mt-0.5">🍪</span>
                <div>
                    <p class="text-sm text-gray-700 font-medium">{{ __('app.cookie_banner.title') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                        {{ __('app.cookie_banner.desc') }}
                        <a href="{{ url('/cookie-policy') }}" class="text-slate-800 hover:text-slate-950 underline">{{ __('app.cookie_banner.policy') }}</a>
                        | <a href="{{ url('/privacy') }}" class="text-slate-800 hover:text-slate-950 underline">{{ __('app.cookie_banner.privacy') }}</a>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                <button onclick="openCookieSettings()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-lg transition flex-1 sm:flex-none">
                    {{ __('app.cookie_banner.customize') }}
                </button>
                <button onclick="rejectCookieConsent()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex-1 sm:flex-none">
                    {{ __('app.cookie_banner.reject') }}
                </button>
                <button onclick="acceptCookieConsent()" class="px-5 py-2 bg-gradient-to-r from-slate-800 to-slate-950 text-white text-sm font-medium rounded-lg hover:from-slate-900 hover:to-black transition shadow-sm flex-1 sm:flex-none">
                    {{ __('app.cookie_banner.accept_all') }}
                </button>
            </div>
        </div>
    </div>
</div>

<div id="cookie-settings-overlay" class="fixed inset-0 z-[60] bg-black/40 hidden items-center justify-center p-4" onclick="closeCookieSettings(event)">
    <div id="cookie-settings-panel" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl">
            <h3 class="text-base font-semibold text-gray-900">🍪 {{ __('app.cookie_banner.prefs_title') }}</h3>
            <button onclick="closeCookieSettings(event)" class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 space-y-5">
            <p class="text-xs text-gray-500">{{ __('app.cookie_banner.prefs_hint') }}</p>

            <div class="space-y-4">
                <div class="flex items-start gap-3 bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">🍪</span>
                            <span class="text-sm font-medium text-gray-900">{{ __('app.legal_page.cookie_cat_necessary') }}</span>
                            <span class="text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">{{ __('app.legal_page.cookie_always_on') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.cookie_banner.necessary_desc') }}</p>
                    </div>
                    <div class="shrink-0">
                        <input type="checkbox" checked disabled class="opacity-50 cursor-not-allowed w-4 h-4 rounded border-gray-300">
                    </div>
                </div>

                <div class="cookie-category flex items-start gap-3 bg-white rounded-xl p-4 border border-gray-100 hover:border-gray-200 transition cursor-pointer" data-category="functional" onclick="toggleCategory('functional')">
                    <div class="flex-1 pointer-events-none">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">⚙️</span>
                            <span class="text-sm font-medium text-gray-900">{{ __('app.legal_page.cookie_cat_functional') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.cookie_banner.functional_desc') }}</p>
                    </div>
                    <div class="shrink-0 pointer-events-none">
                        <input type="checkbox" id="cookie-cat-functional" class="cookie-category-checkbox w-4 h-4 rounded border-gray-300 text-slate-800 focus:ring-slate-400">
                    </div>
                </div>

                <div class="cookie-category flex items-start gap-3 bg-white rounded-xl p-4 border border-gray-100 hover:border-gray-200 transition cursor-pointer" data-category="analytics" onclick="toggleCategory('analytics')">
                    <div class="flex-1 pointer-events-none">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">📊</span>
                            <span class="text-sm font-medium text-gray-900">{{ __('app.legal_page.cookie_cat_analytics') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.cookie_banner.analytics_desc') }}</p>
                    </div>
                    <div class="shrink-0 pointer-events-none">
                        <input type="checkbox" id="cookie-cat-analytics" class="cookie-category-checkbox w-4 h-4 rounded border-gray-300 text-slate-800 focus:ring-slate-400">
                    </div>
                </div>

                <div class="cookie-category flex items-start gap-3 bg-white rounded-xl p-4 border border-gray-100 hover:border-gray-200 transition cursor-pointer" data-category="marketing" onclick="toggleCategory('marketing')">
                    <div class="flex-1 pointer-events-none">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">📢</span>
                            <span class="text-sm font-medium text-gray-900">{{ __('app.legal_page.cookie_cat_marketing') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.cookie_banner.marketing_desc') }}</p>
                    </div>
                    <div class="shrink-0 pointer-events-none">
                        <input type="checkbox" id="cookie-cat-marketing" class="cookie-category-checkbox w-4 h-4 rounded border-gray-300 text-slate-800 focus:ring-slate-400">
                    </div>
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 flex items-center justify-between gap-3 rounded-b-2xl">
            <button onclick="rejectCookieConsent()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition">{{ __('app.cookie_banner.reject_all') }}</button>
            <div class="flex items-center gap-2">
                <button onclick="saveCookiePreferences()" class="px-5 py-2 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 transition shadow-sm">
                    {{ __('app.cookie_banner.save') }}
                </button>
                <button onclick="acceptCookieConsent()" class="px-5 py-2 bg-gradient-to-r from-slate-800 to-slate-950 text-white text-sm font-medium rounded-lg hover:from-slate-900 hover:to-black transition shadow-sm">
                    {{ __('app.cookie_banner.accept_all') }}
                </button>
            </div>
        </div>
    </div>
</div>

<button id="cookie-settings-btn" onclick="openCookieSettings()" class="fixed bottom-4 right-4 z-50 w-10 h-10 bg-white rounded-full shadow-lg border border-gray-200 flex items-center justify-center text-base hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 hidden" title="{{ __('app.cookie_banner.btn_title') }}" aria-label="{{ __('app.cookie_banner.btn_title') }}">
    🍪
</button>

<script>
(function() {
    var KEY = 'cookie_consent';
    var GIVEN_KEY = 'cookie_consent_given';
    var BANNER_KEY = 'cookie_consent_banner_closed';

    var existing = localStorage.getItem(KEY);
    if (!existing) {
        var vueConsent = localStorage.getItem(GIVEN_KEY);
        if (!vueConsent) {
            showBanner();
        } else {
            try { migrateConsent(JSON.parse(vueConsent)); } catch(e) { showBanner(); }
        }
    } else {
        showSettingsBtn();
    }

    function showBanner() {
        var banner = document.getElementById('cookie-banner');
        if (banner) banner.classList.remove('hidden');
    }

    function showSettingsBtn() {
        // 已隐藏 Cookie 设置悬浮按钮
    }

    function hideBanner() {
        var banner = document.getElementById('cookie-banner');
        if (banner) banner.classList.add('hidden');
    }

    function migrateConsent(old) {
        if (old && old.action === 'accepted') {
            saveConsent({
                action: 'accepted',
                categories: ['functional', 'analytics', 'marketing'],
                timestamp: old.timestamp || Date.now(),
            });
        } else {
            saveConsent({
                action: 'rejected',
                categories: [],
                timestamp: old.timestamp || Date.now(),
            });
        }
    }

    function saveConsent(data) {
        localStorage.setItem(KEY, data.action);
        localStorage.setItem(GIVEN_KEY, JSON.stringify(data));
        hideBanner();
        showSettingsBtn();
        closePanel();
        applyConsent(data);
    }

    window.acceptCookieConsent = function() {
        saveConsent({
            action: 'accepted',
            categories: ['functional', 'analytics', 'marketing'],
            timestamp: Date.now(),
        });
    };

    window.rejectCookieConsent = function() {
        saveConsent({
            action: 'rejected',
            categories: [],
            timestamp: Date.now(),
        });
    };

    window.openCookieSettings = function(e) {
        if (e) e.preventDefault();
        var overlay = document.getElementById('cookie-settings-overlay');
        if (overlay) overlay.classList.remove('hidden');
        restoreCheckboxes();
        document.body.style.overflow = 'hidden';
    };

    window.closeCookieSettings = function(e) {
        if (e) e.preventDefault();
        closePanel();
    };

    window.saveCookiePreferences = function() {
        var cats = [];
        document.querySelectorAll('.cookie-category-checkbox:checked').forEach(function(cb) {
            var cat = cb.closest('.cookie-category');
            if (cat) cats.push(cat.dataset.category);
        });
        saveConsent({
            action: cats.length > 0 ? 'accepted' : 'rejected',
            categories: cats,
            timestamp: Date.now(),
        });
    };

    window.toggleCategory = function(category) {
        var cb = document.getElementById('cookie-cat-' + category);
        if (cb) cb.checked = !cb.checked;
    };

    function restoreCheckboxes() {
        try {
            var data = JSON.parse(localStorage.getItem(GIVEN_KEY) || '{}');
            var cats = data.categories || [];
            document.querySelectorAll('.cookie-category-checkbox').forEach(function(cb) {
                var cat = cb.closest('.cookie-category');
                if (cat && cats.includes(cat.dataset.category)) {
                    cb.checked = true;
                }
            });
        } catch(e) {}
    }

    function closePanel() {
        var overlay = document.getElementById('cookie-settings-overlay');
        if (overlay) overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function applyConsent(data) {
        var event = new CustomEvent('cookieConsentChanged', { detail: data });
        document.dispatchEvent(event);
    }

    try {
        var saved = localStorage.getItem(GIVEN_KEY);
        if (saved) {
            var data = JSON.parse(saved);
            if (data && data.action) {
                applyConsent(data);
            }
        }
    } catch(e) {}
})();
</script>
