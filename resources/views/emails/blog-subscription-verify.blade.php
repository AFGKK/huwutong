<x-mail::message>
# {{ __('app.mail.blog_verify.heading') }}

{{ __('app.mail.blog_verify.body') }}

**{{ $types }}**

{{ __('app.mail.blog_verify.confirm_hint') }}

<x-mail::button :url="$verifyUrl">
{{ __('app.mail.blog_verify.confirm') }}
</x-mail::button>

{{ __('app.mail.blog_verify.ignore') }}

---

{{ __('app.mail.blog_verify.unsubscribe') }} <a href="{{ $unsubscribeUrl }}" style="color: #909399;">{{ __('app.mail.blog_verify.unsubscribe_link') }}</a>
</x-mail::message>
