<nav class="fixed top-0 w-full bg-white/90 backdrop-blur-xl z-50 border-b border-gray-200/80 shadow-[0_2px_12px_-6px_rgba(0,0,0,0.12)]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 md:h-20">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center shadow-md group-hover:shadow-lg group-hover:scale-105 transition-all duration-300">
                    <span class="text-white font-bold text-base">互</span>
                </div>
                <span class="font-bold text-xl text-gray-900">{{ site_setting('site_name', '互物通') }}</span>
            </a>
            <div class="hidden md:flex items-center gap-1">
                <a href="{{ url('/') }}" class="nav-link{{ request()->is('/') ? ' text-primary-600 bg-primary-50/50' : '' }}">首页</a>
                <a href="{{ url('/pricing') }}" class="nav-link{{ request()->is('pricing') ? ' text-primary-600 bg-primary-50/50' : '' }}">定价页</a>
                <a href="{{ url('/products') }}" class="nav-link{{ request()->is('products*') ? ' text-primary-600 bg-primary-50/50' : '' }}">产品商城</a>
                <a href="/build/community" class="nav-link{{ request()->is('build/community*') ? ' text-primary-600 bg-primary-50/50' : '' }}">社区</a>
                <a href="/build/channels" class="nav-link{{ request()->is('build/channels*') ? ' text-primary-600 bg-primary-50/50' : '' }}">互物号</a>
                <a href="{{ url('/search') }}" class="nav-link{{ request()->is('search') ? ' text-primary-600 bg-primary-50/50' : '' }}">互物搜索</a>
                <a href="{{ url('/license/query') }}" class="nav-link{{ request()->is('license/query') ? ' text-primary-600 bg-primary-50/50' : '' }}">授权查询</a>
                <div class="ml-4 flex items-center gap-3" id="nav-auth-desktop">
                    <a href="/build/login" class="text-sm font-medium text-gray-600 hover:text-primary-600 transition px-3 py-2" id="nav-login">登录</a>
                    <a href="/build/register" class="text-sm font-medium bg-gradient-to-r from-primary-500 to-primary-600 text-white px-6 py-2.5 rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5" id="nav-register">免费注册</a>
                </div>
            </div>
            <button class="md:hidden p-2.5 rounded-xl hover:bg-gray-100 transition" onclick="document.getElementById('nav-mobile').classList.toggle('hidden')">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div id="nav-mobile" class="hidden md:hidden border-t border-gray-100 bg-white/95 backdrop-blur-xl max-h-[85vh] overflow-y-auto">
        <div class="px-4 py-4 space-y-1">
            <a href="{{ url('/') }}" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('/') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">🏠 首页</a>
            <a href="{{ url('/pricing') }}" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('pricing') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">💰 定价页</a>
            <a href="{{ url('/products') }}" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('products*') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">🛒 产品商城</a>
            <a href="/build/community" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('build/community*') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">社区</a>
            <a href="/build/channels" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('build/channels*') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">互物号</a>
            <a href="{{ url('/search') }}" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('search') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">🔍 互物搜索</a>
            <a href="{{ url('/license/query') }}" class="block px-4 py-2.5 rounded-xl text-sm{{ request()->is('license/query') ? ' text-primary-600 bg-primary-50 font-semibold' : ' text-gray-600 hover:text-primary-600 hover:bg-primary-50' }} transition font-medium">🔑 授权查询</a>
            <hr class="my-2 border-gray-100">
            <a href="/build/login" class="block px-4 py-2.5 rounded-xl text-sm text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium" id="nav-mobile-login">登录</a>
            <a href="/build/register" class="block text-center mt-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white px-5 py-2.5 rounded-xl hover:from-primary-600 hover:to-primary-700 transition font-medium shadow-md" id="nav-mobile-register">免费注册</a>
        </div>
    </div>
</nav>
<style>
a.nav-link { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #4b5563; border-radius: 0.5rem; transition: all 0.2s; text-decoration: none !important; }
a.nav-link:hover { color: #2563eb; background: rgba(37,99,235,0.05); }
</style>

<script>
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

            var name = user.name || '用户';
            var avatar = user.avatar_url || user.avatar || '';
            var email = user.email || '';

            // 桌面端：替换登录/注册为用户信息
            var desktopContainer = document.getElementById('nav-auth-desktop');
            if (desktopContainer) {
                desktopContainer.innerHTML = '<div class="relative group">' +
                    '<button class="flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-gray-100 transition" onclick="document.getElementById(\'user-dropdown\').classList.toggle(\'hidden\')">' +
                    (avatar ? '<img src="' + avatar + '" class="w-8 h-8 rounded-full object-cover border-2 border-primary-100" onerror="this.style.display=\'none\'" />' : '<div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white text-sm font-bold">' + name.charAt(0) + '</div>') +
                    '<span class="text-sm font-medium text-gray-700 hidden sm:inline">' + name + '</span>' +
                    '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>' +
                    '</button>' +
                    '<div id="user-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">' +
                    '<div class="px-4 py-2.5 border-b border-gray-50"><div class="text-sm font-medium text-gray-900 truncate">' + name + '</div><div class="text-xs text-gray-400 truncate">' + email + '</div></div>' +
                    '<a href="/build/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>管理后台</a>' +
                    '<a href="/build/portal" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>客户门户</a>' +
                    '<hr class="my-1 border-gray-50">' +
                    '<button onclick="localStorage.removeItem(\'auth_token\');localStorage.removeItem(\'user\');location.reload()" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:text-red-600 hover:bg-red-50 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>退出登录</button>' +
                    '</div></div>';
            }

            // 移动端：替换登录/注册为用户信息
            var mobileLogin = document.getElementById('nav-mobile-login');
            var mobileRegister = document.getElementById('nav-mobile-register');
            if (mobileLogin && mobileRegister) {
                mobileLogin.outerHTML = '<div class="flex items-center gap-3 px-4 py-3 border-b border-gray-50">' +
                    (avatar ? '<img src="' + avatar + '" class="w-10 h-10 rounded-full object-cover border-2 border-primary-100" onerror="this.style.display=\'none\'" />' : '<div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold">' + name.charAt(0) + '</div>') +
                    '<div class="flex-1 min-w-0"><div class="text-sm font-medium text-gray-900 truncate">' + name + '</div><div class="text-xs text-gray-400 truncate">' + email + '</div></div>' +
                    '</div>';
                mobileRegister.outerHTML = '<a href="/build/dashboard" class="block px-4 py-2.5 rounded-xl text-sm text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">📊 管理后台</a>' +
                    '<a href="/build/portal" class="block px-4 py-2.5 rounded-xl text-sm text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">👤 客户门户</a>' +
                    '<hr class="my-1 border-gray-50">' +
                    '<button onclick="localStorage.removeItem(\'auth_token\');localStorage.removeItem(\'user\');location.reload()" class="w-full text-left px-4 py-2.5 rounded-xl text-sm text-red-500 hover:text-red-600 hover:bg-red-50 transition font-medium">🚪 退出登录</button>';
            }

            // 点击外部关闭下拉
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
