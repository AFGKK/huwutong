@if((string) site_setting('legal_gdpr_enabled', '1') === '1')
@include('public.partials.cookie-banner')
@endif
@php
    $siteName = site_setting('site_name', __('app.app_name'));
    $siteDesc = site_setting('site_description', __('app.app_slogan'));
    $contactEmail = site_setting('contact_email', 'support@huwutong.com');
    $socialGithub = trim((string) site_setting('social_github', ''));
    $socialTwitter = trim((string) site_setting('social_twitter', ''));
    $socialWechat = trim((string) site_setting('social_wechat', ''));
    $socialWeibo = trim((string) site_setting('social_weibo', ''));
    $brandChar = mb_substr($siteName, 0, 1) ?: mb_substr(__('app.app_name'), 0, 1);
    $wechatIsUrl = $socialWechat !== '' && (str_starts_with($socialWechat, 'http://') || str_starts_with($socialWechat, 'https://'));

    $footerNav = ['footer' => [], 'social' => [], 'bottom' => []];
    try {
        if (class_exists(\App\Services\FooterNavService::class)) {
            $footerNav = app(\App\Services\FooterNavService::class)->getPublic();
        }
    } catch (\Throwable $e) {
        // keep defaults
    }
    $navFooter = $footerNav['footer'] ?? [];
    $navSocial = $footerNav['social'] ?? [];
    $navBottom = $footerNav['bottom'] ?? [];
@endphp
<footer class="bg-gray-900 text-gray-400 pt-14 pb-8 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-gray-800/20 to-transparent pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        {{-- 品牌 + 三栏导航 --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">
            {{-- 品牌区 --}}
            <div class="md:col-span-2 lg:col-span-1">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 mb-4 group">
                    @php $footerLogo = site_setting('logo_url'); @endphp
                    @if($footerLogo)
                    <img src="{{ $footerLogo }}" alt="{{ $siteName }}" class="w-9 h-9 rounded-xl object-contain bg-white/10 p-0.5">
                    @else
                    <div class="w-9 h-9 bg-slate-900 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform duration-300">
                        <span class="text-white font-bold text-base">{{ $brandChar }}</span>
                    </div>
                    @endif
                    <span class="font-bold text-xl text-white">{{ $siteName }}</span>
                </a>
                <p class="text-sm leading-relaxed text-gray-400 max-w-sm">{{ $siteDesc }}</p>

                <div class="mt-5">
                    <a href="{{ url('/license/query') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-300 hover:text-slate-200 transition font-medium">
                        {{ __('app.footer.query_cta') }}
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="flex gap-2.5 mt-5 flex-wrap">
                    @if($wechatIsUrl)
                    <a href="{{ $socialWechat }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-green-500 hover:text-white transition-all duration-300" title="{{ __('app.product_detail_page.share_wechat') }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.045c.134 0 .24-.11.24-.245 0-.06-.024-.12-.04-.178l-.325-1.233a.492.492 0 0 1 .178-.553C23.028 18.333 24 16.592 24 14.628c0-3.299-3.063-5.77-7.062-5.77zm-2.18 2.364c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.36 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982z"/></svg>
                    </a>
                    @endif
                    @if($socialWeibo !== '')
                    <a href="{{ $socialWeibo }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-red-500 hover:text-white transition-all duration-300" title="{{ __('app.product_detail_page.share_weibo') }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.739 5.443z"/></svg>
                    </a>
                    @endif
                    @if($socialGithub !== '')
                    <a href="{{ $socialGithub }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-600 hover:text-white transition-all duration-300" title="GitHub">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
                    </a>
                    @endif
                    @if($socialTwitter !== '')
                    <a href="{{ $socialTwitter }}" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-sky-500 hover:text-white transition-all duration-300" title="X / Twitter">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.727-8.894L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>
                    </a>
                    @endif
                    @foreach($navSocial as $sItem)
                        @php
                            $sUrl = $sItem['url'] ?? '';
                            $sLabel = $sItem['label'] ?? 'Social';
                        @endphp
                        @if($sUrl !== '')
                        <a href="{{ $sUrl }}" target="{{ $sItem['target'] ?? '_blank' }}" rel="noopener noreferrer" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-slate-600 hover:text-white transition-all duration-300 text-xs font-medium" title="{{ $sLabel }}">
                            {{ mb_substr($sLabel, 0, 1) }}
                        </a>
                        @endif
                    @endforeach
                    @if($contactEmail !== '')
                    <a href="mailto:{{ $contactEmail }}" class="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-slate-600 hover:text-white transition-all duration-300" title="{{ __('app.nav.contact') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </a>
                    @endif
                </div>
            </div>

            {{-- 产品 --}}
            <div>
                <h4 class="font-semibold text-white mb-5 text-sm tracking-wide">{{ __('app.product.title') }}</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/') }}#features" class="footer-nav-link">{{ __('app.product.features') }}</a></li>
                    <li><a href="{{ url('/products') }}" class="footer-nav-link">{{ __('app.nav.products') }}</a></li>
                    <li><a href="{{ url('/pricing') }}" class="footer-nav-link">{{ __('app.nav.pricing') }}</a></li>
                    <li><a href="{{ url('/compare') }}" class="footer-nav-link">{{ __('app.nav.compare') }}</a></li>
                </ul>
            </div>

            {{-- 资源 --}}
            <div>
                <h4 class="font-semibold text-white mb-5 text-sm tracking-wide">{{ __('app.footer.resources') }}</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/help') }}" class="footer-nav-link">{{ __('app.nav.help') }}</a></li>
                    <li><a href="{{ url('/docs') }}" class="footer-nav-link">{{ __('app.docs_hub_page.crumb') }}</a></li>
                    <li><a href="{{ url('/sdk') }}" class="footer-nav-link">{{ __('app.nav.sdk') }}</a></li>
                    <li><a href="{{ url('/api-docs') }}" class="footer-nav-link">{{ __('app.api_docs_page.title') }}</a></li>
                    <li><a href="{{ url('/blog') }}" class="footer-nav-link">{{ __('app.footer.blog') }}</a></li>
                    <li><a href="{{ url('/license/query') }}" class="footer-nav-link">{{ __('app.nav.license_query') }}</a></li>
                    <li><a href="{{ url('/docs') }}" class="footer-nav-link">{{ __('app.footer.open_platform') }}</a></li>
                </ul>
            </div>

            {{-- 公司 / 可配置页脚主区 --}}
            <div>
                <h4 class="font-semibold text-white mb-5 text-sm tracking-wide">{{ __('app.footer.company') }}</h4>
                <ul class="space-y-3 text-sm">
                    @if(count($navFooter) > 0)
                        @foreach($navFooter as $item)
                        <li>
                            <a href="{{ $item['url'] ?? '#' }}"
                               target="{{ $item['target'] ?? '_self' }}"
                               @if(($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                               class="footer-nav-link">{{ $item['label'] ?? '' }}</a>
                        </li>
                        @endforeach
                    @else
                        <li><a href="{{ url('/about') }}" class="footer-nav-link">{{ __('app.nav.about') }}</a></li>
                        <li><a href="{{ url('/contact') }}" class="footer-nav-link">{{ __('app.nav.contact') }}</a></li>
                        <li><a href="{{ url('/build/status') }}" class="footer-nav-link">{{ __('app.footer.status_page') }}</a></li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- 底栏：版权 + 法律 + 备案 --}}
        <div class="border-t border-gray-800 mt-12 pt-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 text-sm">
                <p class="text-gray-500 text-center lg:text-left">
                    {{ site_setting('footer_copyright', __('app.footer.copyright', ['year' => date('Y')])) }}
                </p>
                <div class="flex flex-wrap items-center justify-center lg:justify-end gap-x-5 gap-y-2 text-xs text-gray-500">
                    @if(count($navBottom) > 0)
                        @foreach($navBottom as $item)
                        <a href="{{ $item['url'] ?? '#' }}"
                           target="{{ $item['target'] ?? '_self' }}"
                           @if(($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                           class="hover:text-gray-300 transition">{{ $item['label'] ?? '' }}</a>
                        @endforeach
                    @else
                        <a href="{{ url('/privacy') }}" class="hover:text-gray-300 transition">{{ __('app.footer.privacy_policy') }}</a>
                        <a href="{{ url('/terms') }}" class="hover:text-gray-300 transition">{{ __('app.footer.terms_of_service') }}</a>
                        <a href="{{ url('/security-policy') }}" class="hover:text-gray-300 transition">{{ __('app.footer.security_policy') }}</a>
                        <a href="{{ url('/cookie-policy') }}" class="hover:text-gray-300 transition">{{ __('app.footer.cookie_policy') }}</a>
                    @endif
                    @if(site_beian_public('icp_beian'))
                    <a href="{{ site_setting('icp_beian_url', 'https://beian.miit.gov.cn/') }}" target="_blank" rel="noopener noreferrer" class="hover:text-gray-300 transition">
                        {{ site_beian_public('icp_beian') }}
                    </a>
                    @endif
                    @if(site_beian_public('gongan_beian'))
                    <a href="{{ site_setting('gongan_beian_url', site_setting('police_beian_url', 'https://www.beian.gov.cn/')) }}" target="_blank" rel="noopener noreferrer" class="hover:text-gray-300 transition">
                        {{ site_beian_public('gongan_beian') }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
.footer-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #9ca3af;
    transition: color 0.2s;
}
.footer-nav-link::before {
    content: '';
    width: 0;
    height: 2px;
    background: #94a3b8;
    transition: width 0.25s;
}
.footer-nav-link:hover {
    color: #fff;
}
.footer-nav-link:hover::before {
    width: 0.5rem;
}
</style>

@if(site_setting('custom_footer_html'))
{!! site_setting('custom_footer_html') !!}
@endif
