@include('public.partials.live-chat')
@include('public.partials.cookie-banner')
<footer class="bg-gray-900 text-gray-400 pt-16 pb-8 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-gray-800/20 to-transparent pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-10">
            <div class="lg:col-span-2">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5 mb-4 group">
                    <div class="w-9 h-9 bg-gradient-to-br from-primary-400 to-primary-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform duration-300">
                        <span class="text-white font-bold text-base">互</span>
                    </div>
                    <span class="font-bold text-xl text-white">{{ site_setting('site_name', '互物通') }}</span>
                </a>
                <p class="text-sm leading-relaxed text-gray-400 max-w-sm">{{ site_setting('site_description') }}</p>
                <div class="flex gap-2.5 mt-5 flex-wrap">
                    <a href="{{ url('/contact') }}" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-green-500 hover:text-white transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg" title="微信">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.045c.134 0 .24-.11.24-.245 0-.06-.024-.12-.04-.178l-.325-1.233a.492.492 0 0 1 .178-.553C23.028 18.333 24 16.592 24 14.628c0-3.299-3.063-5.77-7.062-5.77zm-2.18 2.364c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.36 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982z"/></svg>
                    </a>
                    <a href="https://weibo.com/huwutong" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-red-500 hover:text-white transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg" title="微博">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.739 5.443z"/></svg>
                    </a>
                    <a href="https://github.com/huwutong" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-600 hover:text-white transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg" title="GitHub">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
                    </a>
                    <a href="mailto:support@huwutong.com" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary-500 hover:text-white transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg" title="邮箱">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-5 text-sm uppercase tracking-wider">产品</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/') }}#features" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>产品特性</a></li>
                    <li><a href="{{ url('/products') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>产品商城</a></li>
                    <li><a href="{{ url('/pricing') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>定价</a></li>
                    <li><a href="{{ url('/compare') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>竞品对比</a></li>
                    <li><a href="{{ url('/compare-products') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>产品对比</a></li>
                    <li><a href="{{ url('/docs/quickstart') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>快速入门</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-5 text-sm uppercase tracking-wider">开发者</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="/build/open-platform" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>开放平台</a></li>
                    <li><a href="/build/app-marketplace" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>应用市场</a></li>
                    <li><a href="{{ url('/sdk') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>SDK 下载</a></li>
                    <li><a href="{{ url('/blog') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>开发者博客</a></li>
                    <li><a href="{{ url('/search') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>互物搜索</a></li>
                    <li><a href="{{ url('/help') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>帮助中心</a></li>
                    <li><a href="{{ url('/license/query') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>授权查询</a></li>
                    <li><a href="/build/login" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>管理后台</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-5 text-sm uppercase tracking-wider">公司</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/about') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>关于我们</a></li>
                    <li><a href="{{ url('/contact') }}" class="text-gray-400 hover:text-white transition flex items-center gap-2 group"><span class="w-0 group-hover:w-2 h-0.5 bg-primary-400 transition-all duration-300"></span>联系我们</a></li>
                    <li><a href="{{ url('/privacy') }}" class="hover:text-white transition">隐私政策</a></li>
                    <li><a href="{{ url('/security-policy') }}" class="hover:text-white transition">安全策略</a></li>
                    <li><a href="{{ url('/hall-of-fame') }}" class="hover:text-white transition">安全致谢</a></li>
                    <li><a href="{{ url('/terms') }}" class="hover:text-white transition">服务条款</a></li>
                    <li><a href="{{ url('/cookie-policy') }}" class="hover:text-white transition">Cookie 政策</a></li>
                    <li><a href="{{ url('/accessibility') }}" class="hover:text-white transition">无障碍声明</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-12 pt-8 text-sm text-center">
            <p>{{ site_setting('footer_copyright') }}</p>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-gray-500">
                @if(site_setting('icp_beian'))
                <a href="{{ site_setting('icp_beian_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 hover:text-gray-300 transition">
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"/></svg>
                    {{ site_setting('icp_beian') }}
                </a>
                @endif
                @if(site_setting('gongan_beian'))
                <a href="{{ site_setting('gongan_beian_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 hover:text-gray-300 transition">
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ site_setting('gongan_beian') }}
                </a>
                @endif
            </div>
        </div>
    </div>
</footer>

@if(site_setting('custom_footer_html'))
{!! site_setting('custom_footer_html') !!}
@endif
