<nav class="fixed top-0 w-full bg-white/90 backdrop-blur-xl z-50 border-b border-gray-200/80 shadow-[0_2px_12px_-6px_rgba(0,0,0,0.12)]" role="navigation" aria-label="{{ __('app.nav.home') }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 md:h-20">
            <a href="{{ url('/') }}" class="flex items-center gap-2 group">
                @php $navLogo = site_setting('logo_url'); @endphp
                @if($navLogo)
                <img src="{{ $navLogo }}" alt="{{ site_setting('site_name', __('app.app_name')) }}" class="w-8 h-8 md:w-9 md:h-9 rounded-xl object-contain shadow-md group-hover:shadow-lg group-hover:scale-105 transition-all duration-300 bg-white" />
                @else
                <div class="w-8 h-8 md:w-9 md:h-9 bg-gradient-to-br from-slate-800 to-slate-950 rounded-xl flex items-center justify-center shadow-md group-hover:shadow-lg group-hover:scale-105 transition-all duration-300">
                    <span class="text-white font-bold text-sm md:text-base">{{ substr(__('app.app_name'), 0, 1) }}</span>
                </div>
                @endif
                <span class="font-bold text-base sm:text-lg md:text-xl text-gray-900 truncate max-w-[120px] sm:max-w-[180px] md:max-w-none">{{ site_setting('site_name', __('app.app_name')) }}</span>
            </a>
            <div class="hidden lg:flex items-center gap-1">
                <a href="{{ url('/') }}" class="nav-link{{ request()->is('/') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.nav.home') }}</a>
                <a href="{{ url('/pricing') }}" class="nav-link{{ request()->is('pricing') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.pricing.title') }}</a>
                <a href="{{ url('/products') }}" class="nav-link{{ request()->is('products*') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.nav.products') }}</a>
                <a href="{{ url('/help') }}" class="nav-link{{ request()->is('help*') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.nav.help') }}</a>
                <div class="relative" id="nav-more-desktop">
                    <button type="button" id="nav-more-btn" onclick="toggleNavMore()" class="nav-link flex items-center gap-1" aria-haspopup="true" aria-expanded="false" aria-controls="nav-more-dropdown">
                        {{ __('app.nav.more') }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="nav-more-dropdown" class="hidden absolute left-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-50">
                        <a href="/build/community" class="block px-3 py-2 text-sm {{ request()->is('build/community*') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1">{{ __('app.nav.community') }}</a>
                        <a href="/build/channels" class="block px-3 py-2 text-sm {{ request()->is('build/channels*') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1">{{ __('app.nav.blog') }}</a>
                        <a href="{{ url('/search') }}" class="block px-3 py-2 text-sm {{ request()->is('search') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1">{{ __('app.nav.search') }}</a>
                        <a href="{{ url('/license/query') }}" class="block px-3 py-2 text-sm {{ request()->is('license/query') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1">{{ __('app.nav.license_query') }}</a>
                        <a href="{{ url('/sdk') }}" class="block px-3 py-2 text-sm {{ request()->is('sdk') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1">{{ __('app.nav.sdk') }}</a>
                        <a href="{{ url('/contact') }}" class="block px-3 py-2 text-sm {{ request()->is('contact') ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1">{{ __('app.nav.contact') }}</a>
                    </div>
                </div>
                <div class="ml-3 flex items-center gap-2" id="nav-auth-desktop">
                    <a href="/build/login" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition px-2.5 py-2" id="nav-login">{{ __('app.auth.login_btn') }}</a>
                    <a href="/build/register" class="text-sm font-medium bg-slate-900 text-white px-5 py-2 rounded-xl hover:bg-slate-800 transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5" id="nav-register">{{ __('app.auth.register_btn') }}</a>
                </div>
                <!-- D-22: 语言切换（桌面端） -->
                <div class="ml-2 relative" id="lang-switcher-desktop">
                    <button id="lang-switcher-btn" type="button" onclick="toggleLangDropdown()" class="flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-sm font-medium text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition" aria-label="{{ __('app.language.switch_language') }}" aria-haspopup="true" aria-expanded="false">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span id="lang-current-label">{{ ($localeNames ?? [])[app()->getLocale()] ?? __('app.language.zh_CN') }}</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="lang-dropdown" class="hidden absolute right-0 mt-1 w-36 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-50">
                        @foreach(['zh_CN', 'en'] as $langCode)
                        <a href="?lang={{ $langCode }}"
                           class="block px-3 py-2 text-sm {{ app()->getLocale() === $langCode ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1"
                           onclick="event.preventDefault(); switchLang('{{ $langCode }}')"
                           data-lang="{{ $langCode }}">
                            {{ ($localeNames ?? [])[$langCode] ?? $langCode }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- 平板(768-1024px)：与桌面一致的主入口 + 更多 -->
            <div class="hidden md:flex lg:hidden items-center gap-0.5">
                <a href="{{ url('/') }}" class="nav-link nav-link-compact{{ request()->is('/') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.nav.home') }}</a>
                <a href="{{ url('/pricing') }}" class="nav-link nav-link-compact{{ request()->is('pricing') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.pricing.title') }}</a>
                <a href="{{ url('/products') }}" class="nav-link nav-link-compact{{ request()->is('products*') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.nav.products') }}</a>
                <a href="{{ url('/help') }}" class="nav-link nav-link-compact{{ request()->is('help*') ? ' text-slate-900 bg-slate-100' : '' }}">{{ __('app.nav.help') }}</a>
                <div class="relative" id="nav-more-tablet">
                    <button type="button" id="nav-more-btn-tablet" onclick="toggleNavMoreTablet()" class="nav-link nav-link-compact flex items-center gap-0.5" aria-haspopup="true" aria-expanded="false" aria-controls="nav-more-dropdown-tablet">
                        {{ __('app.nav.more') }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="nav-more-dropdown-tablet" class="hidden absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-50">
                        <a href="/build/community" class="block px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition rounded-lg mx-1">{{ __('app.nav.community') }}</a>
                        <a href="/build/channels" class="block px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition rounded-lg mx-1">{{ __('app.nav.blog') }}</a>
                        <a href="{{ url('/search') }}" class="block px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition rounded-lg mx-1">{{ __('app.nav.search') }}</a>
                        <a href="{{ url('/license/query') }}" class="block px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition rounded-lg mx-1">{{ __('app.nav.license_query') }}</a>
                        <a href="{{ url('/sdk') }}" class="block px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition rounded-lg mx-1">{{ __('app.nav.sdk') }}</a>
                        <a href="{{ url('/contact') }}" class="block px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition rounded-lg mx-1">{{ __('app.nav.contact') }}</a>
                    </div>
                </div>
                <div class="flex items-center gap-1 ml-1" id="nav-auth-tablet">
                    <a href="/build/login" class="text-xs font-medium text-slate-600 hover:text-slate-900 transition px-2 py-1.5" id="nav-login-tablet">{{ __('app.auth.login_btn') }}</a>
                    <a href="/build/register" class="text-xs font-medium bg-slate-900 text-white px-3.5 py-1.5 rounded-xl hover:bg-slate-800 transition-all duration-300 shadow-md" id="nav-register-tablet">{{ __('app.auth.register_btn') }}</a>
                </div>
                <div class="relative ml-0.5">
                    <button type="button" onclick="toggleLangDropdownMobile()" class="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition" aria-label="{{ __('app.language.switch_language') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-2 md:hidden relative">
                <button type="button" id="lang-switcher-btn-mobile" onclick="toggleLangDropdownMobile()" class="p-2 rounded-xl hover:bg-slate-100 transition text-slate-500" aria-label="{{ __('app.language.switch_language') }}" aria-haspopup="true" aria-expanded="false">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <button type="button" class="p-2.5 rounded-xl hover:bg-gray-100 transition" aria-expanded="false" aria-controls="nav-mobile" id="nav-mobile-toggle" onclick="toggleNavMobile()">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    {{-- 平板/移动端共用语言下拉（挂在 nav 根级，避免被 display:none 父级裁切） --}}
    <div id="lang-dropdown-mobile" class="hidden fixed right-4 top-16 w-36 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-[60]">
        @foreach(['zh_CN', 'en'] as $langCode)
        <a href="?lang={{ $langCode }}"
           class="block px-3 py-2 text-sm {{ app()->getLocale() === $langCode ? 'text-slate-900 bg-slate-100 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition rounded-lg mx-1"
           onclick="event.preventDefault(); switchLang('{{ $langCode }}')"
           data-lang="{{ $langCode }}">
            {{ ($localeNames ?? [])[$langCode] ?? $langCode }}
        </a>
        @endforeach
    </div>
    <div id="nav-mobile" class="hidden md:hidden border-t border-gray-100 bg-white/95 backdrop-blur-xl max-h-[85dvh] overflow-y-auto overflow-x-clip safe-bottom">
        <div class="px-4 py-3 space-y-0.5">
            <a href="{{ url('/') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('/') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.home') }}</a>
            <a href="{{ url('/pricing') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('pricing') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.pricing.title') }}</a>
            <a href="{{ url('/products') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('products*') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.products') }}</a>
            <a href="{{ url('/help') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('help*') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.help') }}</a>

            <button type="button" class="nav-mobile-group-btn mt-2" aria-expanded="false" aria-controls="nav-group-platform" onclick="toggleNavGroup('nav-group-platform', this)">
                <span>{{ __('app.nav.group_discover') }}</span>
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="nav-group-platform" class="nav-mobile-group-panel hidden space-y-1 pl-1">
                <a href="/build/community" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('build/community*') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.community') }}</a>
                <a href="/build/channels" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('build/channels*') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.blog') }}</a>
                <a href="{{ url('/search') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('search') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.search') }}</a>
            </div>

            <button type="button" class="nav-mobile-group-btn mt-1" aria-expanded="false" aria-controls="nav-group-support" onclick="toggleNavGroup('nav-group-support', this)">
                <span>{{ __('app.nav.group_support') }}</span>
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="nav-group-support" class="nav-mobile-group-panel hidden space-y-1 pl-1">
                <a href="{{ url('/license/query') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('license/query') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.license_query') }}</a>
                <a href="{{ url('/sdk') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('sdk') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.sdk') }}</a>
                <a href="{{ url('/about') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('about') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.about') }}</a>
                <a href="{{ url('/contact') }}" onclick="closeNavMobile()" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('contact') ? ' text-slate-900 bg-slate-100 font-semibold' : ' text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} transition font-medium">{{ __('app.nav.contact') }}</a>
            </div>

            <hr class="my-2 border-gray-100">
            <a href="/build/login" class="block px-4 py-2.5 rounded-xl text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition font-medium" id="nav-mobile-login">{{ __('app.auth.login_btn') }}</a>
            <a href="/build/register" class="block text-center mt-2 bg-slate-900 text-white px-5 py-2.5 rounded-xl hover:bg-slate-800 transition font-medium shadow-md" id="nav-mobile-register">{{ __('app.auth.register_btn') }}</a>
        </div>
    </div>
</nav>
@include('public.partials.announce-banner')
<style>
a.nav-link { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #475569; border-radius: 0.5rem; transition: all 0.2s; text-decoration: none !important; }
a.nav-link.nav-link-compact { padding: 0.4rem 0.55rem; font-size: 0.8125rem; }
a.nav-link:hover, button.nav-link:hover { color: var(--pg-primary); background: rgba(var(--pg-primary-rgb), 0.05); }
button.nav-link { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #475569; border-radius: 0.5rem; transition: all 0.2s; background: transparent; border: 0; cursor: pointer; }
button.nav-link.nav-link-compact { padding: 0.4rem 0.55rem; font-size: 0.8125rem; }
.nav-mobile-group-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.625rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; color: #334155; background: #f8fafc; border: 0; cursor: pointer; }
.nav-mobile-group-btn[aria-expanded="true"] { background: #f1f5f9; color: #0f172a; }
.nav-mobile-group-btn[aria-expanded="true"] svg { transform: rotate(180deg); }
.nav-mobile-group-btn svg { transition: transform 0.2s; }
</style>

<script>
function toggleNavMobile() {
    var panel = document.getElementById('nav-mobile');
    var toggle = document.getElementById('nav-mobile-toggle');
    if (!panel) return;
    panel.classList.toggle('hidden');
    var isOpen = !panel.classList.contains('hidden');
    if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}
function closeNavMobile() {
    var panel = document.getElementById('nav-mobile');
    var toggle = document.getElementById('nav-mobile-toggle');
    if (panel) panel.classList.add('hidden');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
}
function toggleNavGroup(id, btn) {
    var panel = document.getElementById(id);
    if (!panel) return;
    var hidden = panel.classList.toggle('hidden');
    if (btn) btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
}
function toggleNavMore() {
    closeNavMoreTablet();
    var dd = document.getElementById('nav-more-dropdown');
    var btn = document.getElementById('nav-more-btn');
    if (!dd) return;
    dd.classList.toggle('hidden');
    if (btn) btn.setAttribute('aria-expanded', !dd.classList.contains('hidden') ? 'true' : 'false');
}
function closeNavMore() {
    var dd = document.getElementById('nav-more-dropdown');
    var btn = document.getElementById('nav-more-btn');
    if (dd) dd.classList.add('hidden');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}
function toggleNavMoreTablet() {
    closeNavMore();
    var dd = document.getElementById('nav-more-dropdown-tablet');
    var btn = document.getElementById('nav-more-btn-tablet');
    if (!dd) return;
    dd.classList.toggle('hidden');
    if (btn) btn.setAttribute('aria-expanded', !dd.classList.contains('hidden') ? 'true' : 'false');
}
function closeNavMoreTablet() {
    var dd = document.getElementById('nav-more-dropdown-tablet');
    var btn = document.getElementById('nav-more-btn-tablet');
    if (dd) dd.classList.add('hidden');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}
function closeAllLangDropdowns() {
    var desktop = document.getElementById('lang-dropdown');
    var mobile = document.getElementById('lang-dropdown-mobile');
    if (desktop) desktop.classList.add('hidden');
    if (mobile) mobile.classList.add('hidden');
    var btn = document.getElementById('lang-switcher-btn');
    var btnM = document.getElementById('lang-switcher-btn-mobile');
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (btnM) btnM.setAttribute('aria-expanded', 'false');
}
function toggleLangDropdown() {
    var dd = document.getElementById('lang-dropdown');
    var mobile = document.getElementById('lang-dropdown-mobile');
    if (mobile) mobile.classList.add('hidden');
    if (!dd) return;
    dd.classList.toggle('hidden');
    var btn = document.getElementById('lang-switcher-btn');
    if (btn) btn.setAttribute('aria-expanded', !dd.classList.contains('hidden'));
}
function toggleLangDropdownMobile() {
    var dd = document.getElementById('lang-dropdown-mobile');
    var desktop = document.getElementById('lang-dropdown');
    if (desktop) desktop.classList.add('hidden');
    if (!dd) return;
    dd.classList.toggle('hidden');
    var btn = document.getElementById('lang-switcher-btn-mobile');
    if (btn) btn.setAttribute('aria-expanded', !dd.classList.contains('hidden'));
}
function switchLang(code) {
    var done = function() {
        document.cookie = 'locale=' + code + '; path=/; max-age=' + (60*60*24*365) + '; SameSite=Lax';
        var url = new URL(window.location.href);
        url.searchParams.set('lang', code);
        window.location.href = url.toString();
    };
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/locale/switch');
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.onload = done;
    xhr.onerror = done;
    xhr.send(JSON.stringify({ locale: code }));
}
document.addEventListener('click', function(e) {
    if (e.target.closest('#lang-switcher-btn') || e.target.closest('#lang-switcher-btn-mobile') || e.target.closest('#lang-dropdown') || e.target.closest('#lang-dropdown-mobile') || e.target.closest('#lang-switcher-desktop')) {
        return;
    }
    closeAllLangDropdowns();
    if (!e.target.closest('#nav-more-desktop')) {
        closeNavMore();
    }
    if (!e.target.closest('#nav-more-tablet')) {
        closeNavMoreTablet();
    }
});
</script>

<script>
// 已登录用户替换登录按钮
(function() {
    var token = localStorage.getItem('auth_token');
    if (!token) return;

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/user');
    xhr.setRequestHeader('Authorization', 'Bearer ' + token);
    xhr.onload = function() {
        if (xhr.status !== 200) return;
        try {
            var data = JSON.parse(xhr.responseText);
            var user = data.data || data.user;
            if (!user) return;

            var name = user.name || '{{ __('app.auth.login_btn') }}';
            var avatar = user.avatar_url || user.avatar || '';
            var email = user.email || '';

            // 桌面端用户菜单（同原实现...）
            var desktopContainer = document.getElementById('nav-auth-desktop');
            if (desktopContainer) {
                desktopContainer.innerHTML = '<div class="relative group">' +
                    '<button class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-gray-100 transition" onclick="document.getElementById(\'user-dropdown\').classList.toggle(\'hidden\')">' +
                    (avatar ? '<img src="' + avatar + '" class="w-7 h-7 md:w-8 md:h-8 rounded-full object-cover border-2 border-slate-200" onerror="this.style.display=\'none\'" />' : '<div class="w-7 h-7 md:w-8 md:h-8 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white text-xs md:text-sm font-bold">' + name.charAt(0) + '</div>') +
                    '<span class="text-sm font-medium text-gray-700 hidden lg:inline">' + name + '</span>' +
                    '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>' +
                    '</button>' +
                    '<div id="user-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">' +
                    '<div class="px-4 py-2.5 border-b border-gray-50"><div class="text-sm font-medium text-gray-900 truncate">' + name + '</div><div class="text-xs text-gray-400 truncate">' + email + '</div></div>' +
                    '<a href="/build/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>{{ __('app.nav.admin') }}</a>' +
                    '<a href="/build/portal" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>{{ __('app.nav.portal') }}</a>' +
                    '<a href="/build/user-chat" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>{{ __('app.nav.messages') }}</a>' +
                    '<hr class="my-1 border-gray-50">' +
                    '<button onclick="localStorage.removeItem(\'auth_token\');localStorage.removeItem(\'user\');location.reload()" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:text-red-600 hover:bg-red-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>{{ __('app.auth.logout') }}</button>' +
                    '</div></div>';
            }
            var tabletContainer = document.getElementById('nav-auth-tablet');
            if (tabletContainer) {
                tabletContainer.innerHTML = '<a href="/build/dashboard" class="text-xs font-medium text-slate-600 hover:text-slate-900 transition px-2 py-1.5" title="{{ __('app.nav.admin') }}">' +
                    (avatar ? '<img src="' + avatar + '" class="w-6 h-6 rounded-full object-cover inline-block align-middle border border-slate-200" onerror="this.style.display=\'none\'" />' : '<span class="inline-block w-6 h-6 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white text-xs font-bold leading-6 text-center align-middle">' + name.charAt(0) + '</span>') +
                    '</a>';
            }
            var mobileLogin = document.getElementById('nav-mobile-login');
            var mobileRegister = document.getElementById('nav-mobile-register');
            if (mobileLogin && mobileRegister) {
                mobileLogin.outerHTML = '<div class="flex items-center gap-3 px-4 py-3 border-b border-gray-50">' +
                    (avatar ? '<img src="' + avatar + '" class="w-10 h-10 rounded-full object-cover border-2 border-slate-200" onerror="this.style.display=\'none\'" />' : '<div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white font-bold">' + name.charAt(0) + '</div>') +
                    '<div class="flex-1 min-w-0"><div class="text-sm font-medium text-gray-900 truncate">' + name + '</div><div class="text-xs text-gray-400 truncate">' + email + '</div></div>' +
                    '</div>';
                mobileRegister.outerHTML = '<a href="/build/dashboard" class="block px-4 py-2.5 rounded-xl text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition font-medium">{{ __('app.nav.admin') }}</a>' +
                    '<a href="/build/portal" class="block px-4 py-2.5 rounded-xl text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition font-medium">{{ __('app.nav.portal') }}</a>' +
                    '<a href="/build/user-chat" class="block px-4 py-2.5 rounded-xl text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition font-medium">{{ __('app.nav.messages') }}</a>' +
                    '<hr class="my-1 border-gray-50">' +
                    '<button onclick="localStorage.removeItem(\'auth_token\');localStorage.removeItem(\'user\');location.reload()" class="w-full text-left px-4 py-2.5 rounded-xl text-sm text-red-500 hover:text-red-600 hover:bg-red-50 transition font-medium">{{ __('app.auth.logout') }}</button>';
            }
            document.addEventListener('click', function(e) {
                var dd = document.getElementById('user-dropdown');
                if (dd && !dd.parentElement.contains(e.target)) {
                    dd.classList.add('hidden');
                }
            });
        } catch(e) {}
    };
    xhr.send();
})();
</script>
