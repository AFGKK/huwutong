<x-mail::message>
# {{ $post->title }}

**{{ __('app.mail.blog_published.author') }}:** {{ $post->author }} | **{{ __('app.mail.blog_published.published_at') }}:** {{ $post->published_at->format('Y-m-d') }}

@if($post->excerpt)
{{ $post->excerpt }}
@endif

---

{{ strip_tags(substr($post->content, 0, 500)) }}@if(strlen($post->content) > 500)...
@endif

<x-mail::button :url="url('/blog/' . $post->slug)">
{{ __('app.mail.blog_published.read_more') }}
</x-mail::button>

---

{{ __('app.mail.blog_published.unsubscribe') }} <a href="{{ $unsubscribeUrl }}" style="color: #909399;">{{ __('app.mail.blog_published.unsubscribe_link') }}</a>
</x-mail::message>
