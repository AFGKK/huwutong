<nav class="fixed top-0 w-full bg-white/80 backdrop-blur-xl z-50 border-b border-gray-100/80 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 md:h-20">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center shadow-md group-hover:shadow-lg group-hover:scale-105 transition-all duration-300">
                    <span class="text-white font-bold text-base">互</span>
                </div>
                <span class="font-bold text-xl text-gray-900">{{ site_setting('site_name', '互物通') }}</span>
            </a>
            <div class="hidden md:flex items-center gap-1">
                <a href="{{ url('/') }}#features" class="nav-link">产品特性</a>
                <a href="{{ url('/products') }}" class="nav-link">产品商城</a>
                <a href="{{ url('/pricing') }}" class="nav-link">定价</a>
                <a href="{{ url('/search') }}" class="nav-link">互物搜索</a>
                <a href="{{ url('/license/query') }}" class="nav-link">授权查询</a>
                <a href="/build/open-platform" class="nav-link">开放平台</a>
                <a href="/build/app-marketplace" class="nav-link">应用市场</a>
                <a href="{{ url('/compare') }}" class="nav-link">竞品对比</a>
                <a href="{{ url('/compare-products') }}" class="nav-link">产品对比</a>
                <a href="{{ url('/docs/quickstart') }}" class="nav-link">快速入门</a>
                <a href="{{ url('/sdk') }}" class="nav-link">SDK下载</a>
                <a href="{{ url('/blog') }}" class="nav-link">开发者博客</a>
                <a href="{{ url('/help') }}" class="nav-link">帮助中心</a>
                <a href="{{ url('/about') }}" class="nav-link">关于</a>
                <a href="{{ url('/contact') }}" class="nav-link">联系</a>
                <div class="ml-4 flex items-center gap-3">
                    <a href="/build/login" class="text-sm font-medium text-gray-600 hover:text-primary-600 transition px-3 py-2">登录</a>
                    <a href="/build/register" class="text-sm font-medium bg-gradient-to-r from-primary-500 to-primary-600 text-white px-6 py-2.5 rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5">免费注册</a>
                </div>
            </div>
            <button class="md:hidden p-2.5 rounded-xl hover:bg-gray-100 transition" onclick="document.getElementById('nav-mobile').classList.toggle('hidden')">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div id="nav-mobile" class="hidden md:hidden border-t border-gray-100 bg-white/95 backdrop-blur-xl">
        <div class="px-4 py-4 space-y-1">
            <a href="{{ url('/') }}#features" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">产品特性</a>
            <a href="{{ url('/products') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">产品商城</a>
            <a href="{{ url('/pricing') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">定价</a>
            <a href="{{ url('/search') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">互物搜索</a>
            <a href="{{ url('/license/query') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">授权查询</a>
            <a href="/build/open-platform" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">开放平台</a>
            <a href="/build/app-marketplace" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">应用市场</a>
            <a href="{{ url('/compare') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">竞品对比</a>
            <a href="{{ url('/compare-products') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">产品对比</a>
            <a href="{{ url('/docs/quickstart') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">快速入门</a>
            <a href="{{ url('/sdk') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">SDK下载</a>
            <a href="{{ url('/blog') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">开发者博客</a>
            <a href="{{ url('/help') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">帮助中心</a>
            <a href="{{ url('/about') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">关于</a>
            <a href="{{ url('/contact') }}" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">联系</a>
            <hr class="my-2 border-gray-100">
            <a href="/build/login" class="block px-4 py-3 rounded-xl text-gray-600 hover:text-primary-600 hover:bg-primary-50 transition font-medium">登录</a>
            <a href="/build/register" class="block text-center mt-2 bg-gradient-to-r from-primary-500 to-primary-600 text-white px-5 py-3 rounded-xl hover:from-primary-600 hover:to-primary-700 transition font-medium shadow-md">免费注册</a>
        </div>
    </div>
</nav>
<style>
.nav-link { @apply px-4 py-2 text-sm font-medium text-gray-600 hover:text-primary-600 transition rounded-lg hover:bg-primary-50/50; }
</style>
