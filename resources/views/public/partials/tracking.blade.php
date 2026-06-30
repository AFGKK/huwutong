<!-- ─── SEO / 跟踪 / 验证 代码 (动态管理) ─── -->
@if(site_setting('seo_google_analytics'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ site_setting('seo_google_analytics') }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{{ site_setting('seo_google_analytics') }}');
</script>
@endif

@if(site_setting('tracking_baidu_id'))
<script>
var _hmt = _hmt || [];
(function() {
  var hm = document.createElement('script');
  hm.src = 'https://hm.baidu.com/hm.js?{{ site_setting('tracking_baidu_id') }}';
  var s = document.getElementsByTagName('script')[0];
  s.parentNode.insertBefore(hm, s);
})();
</script>
@endif

@if(site_setting('tracking_meta_pixel'))
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{{ site_setting('tracking_meta_pixel') }}');
fbq('track', 'PageView');
</script>
@endif

@if(site_setting('verify_google'))
<meta name="google-site-verification" content="{{ site_setting('verify_google') }}">
@endif

@if(site_setting('verify_baidu'))
<meta name="baidu-site-verification" content="{{ site_setting('verify_baidu') }}">
@endif

@if(site_setting('verify_bing'))
<meta name="msvalidate.01" content="{{ site_setting('verify_bing') }}">
@endif

@if(site_setting('custom_head_html'))
{{-- 自定义 Head 代码 --}}
{!! site_setting('custom_head_html') !!}
@endif
