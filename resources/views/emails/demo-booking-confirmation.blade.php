@php $appName = __('app.app_name'); @endphp
<x-mail::message>
# {{ __('app.mail.demo_confirm.heading') }}

{{ __('app.mail.demo_confirm.greeting', ['name' => $name]) }}

{{ __('app.mail.demo_confirm.body', ['app' => $appName]) }}

{{ __('app.mail.demo_confirm.sla') }}

{{ __('app.mail.demo_confirm.meanwhile') }}
- 👉 [{{ __('app.mail.demo_confirm.link_register') }}]({{ url('/build/register') }}) {{ __('app.mail.demo_confirm.link_register_hint') }}
- 👉 [{{ __('app.mail.demo_confirm.link_sdk') }}]({{ url('/docs/sdk') }}) {{ __('app.mail.demo_confirm.link_sdk_hint') }}
- 👉 [{{ __('app.mail.demo_confirm.link_pricing') }}]({{ url('/pricing') }}) {{ __('app.mail.demo_confirm.link_pricing_hint') }}

{{ __('app.mail.demo_confirm.questions') }}

---

{{ __('app.mail.demo_confirm.team', ['app' => $appName]) }}
{{ url('/') }}
</x-mail::message>
