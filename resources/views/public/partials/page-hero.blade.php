{{-- Shared public page hero (slate B2B) --}}
@php
    $heroTitle = $heroTitle ?? '';
    $heroSubtitle = $heroSubtitle ?? null;
    $heroCrumb = $heroCrumb ?? $heroTitle;
@endphp
<section class="pt-24 pb-16 md:pb-20 bg-white relative overflow-hidden border-b border-slate-100">
    <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
        <div class="absolute top-10 left-10 w-72 h-72 bg-slate-400 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-slate-500 rounded-full blur-3xl"></div>
    </div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <nav class="flex items-center gap-1.5 text-sm mb-8 text-slate-500">
            <a href="{{ url('/') }}" class="hover:text-slate-900 transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ __('app.pricing.breadcrumb_home') }}
            </a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-800 font-medium">{{ $heroCrumb }}</span>
        </nav>
        <div class="text-center">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 mb-4 tracking-tight">{{ $heroTitle }}</h1>
            @if($heroSubtitle)
            <p class="text-lg text-slate-500 max-w-2xl mx-auto">{{ $heroSubtitle }}</p>
            @endif
        </div>
    </div>
</section>
