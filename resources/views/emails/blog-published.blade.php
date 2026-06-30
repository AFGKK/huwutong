<x-mail::message>
# {{ $post->title }}

**作者:** {{ $post->author }} | **发布时间:** {{ $post->published_at->format('Y-m-d') }}

@if($post->excerpt)
{{ $post->excerpt }}
@endif

---

{{ strip_tags(substr($post->content, 0, 500)) }}@if(strlen($post->content) > 500)...
@endif

<x-mail::button :url="url('/blog/' . $post->slug)">
阅读全文
</x-mail::button>

---

如果你不想再收到此类邮件，可以 <a href="{{ $unsubscribeUrl }}" style="color: #909399;">取消订阅</a>
</x-mail::message>
