<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.hof_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="robots" content="all">
    <meta name="description" content="{{ __('app.hof_page.meta_desc') }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        .hall-of-fame-page { min-height: 50vh; padding-bottom: 60px; }
        .hacker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; margin: 1.5rem 0; }
        .hacker-card { background: #f8f9fa; border-radius: 10px; padding: 1.25rem; text-align: center; border: 1px solid #e9ecef; transition: transform .15s, box-shadow .15s; }
        .hacker-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
        .hacker-card .hacker-avatar { width: 64px; height: 64px; border-radius: 50%; background: var(--pg-primary); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto .75rem; font-size: 1.5rem; font-weight: 700; }
        .hacker-card .hacker-name { font-weight: 700; font-size: 1.05rem; margin-bottom: .25rem; }
        .hacker-card .hacker-handle { color: #666; font-size: .85rem; margin-bottom: .5rem; }
        .rank-badge { display: inline-block; padding: .2em .7em; border-radius: 12px; font-size: .75rem; font-weight: 700; }
        .rank-gold { background: #ffd700; color: #333; }
        .rank-silver { background: #c0c0c0; color: #333; }
        .rank-bronze { background: #cd7f32; color: #fff; }
    </style>
</head>
<body>
@include('public.partials.nav')

@include('public.partials.page-hero', [
    'heroTitle' => __('app.hof_page.title'),
    'heroSubtitle' => __('app.hof_page.subtitle'),
    'heroCrumb' => __('app.hof_page.title'),
])
<div class="hall-of-fame-page" style="padding-top:0">
    <div class="max-w-4xl mx-auto px-4 pb-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8">

            @if($hallOfFame->isEmpty())
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🛡️</div>
                    <p class="text-gray-400 text-lg">{{ __('app.hof_page.empty') }}</p>
                    <p class="mt-3"><a href="/security-policy" class="text-slate-800 hover:text-slate-900 font-medium">{{ __('app.hof_page.join') }}</a></p>
                </div>
            @else
                @foreach(['gold', 'silver', 'bronze'] as $rank)
                    @php $rankEntries = $hallOfFame->where('rank', $rank); @endphp
                    @if($rankEntries->isNotEmpty())
                        <h2 class="text-xl font-bold text-gray-800 mt-8 mb-4 pb-2 border-b-2" style="border-color: {{ $rank === 'gold' ? '#ffd700' : ($rank === 'silver' ? '#c0c0c0' : '#cd7f32') }}">
                            {{ \App\Models\BugBountyHallOfFame::rankLabel($rank) }}
                            <span class="text-sm text-gray-400 font-normal">({{ $rankEntries->count() }})</span>
                        </h2>
                        <div class="hacker-grid">
                            @foreach($rankEntries as $entry)
                                <div class="hacker-card">
                                    <div class="hacker-avatar">{{ strtoupper(substr($entry->hacker_name, 0, 1)) }}</div>
                                    <div class="hacker-name">{{ $entry->hacker_name }}</div>
                                    @if($entry->hacker_handle)
                                        <div class="hacker-handle">@ {{ $entry->hacker_handle }}</div>
                                    @endif
                                    <div class="flex items-center justify-center gap-3 text-sm text-gray-500 mb-2">
                                        <span class="bg-gray-200 px-2 py-0.5 rounded">📄 {{ __('app.hof_page.reports_n', ['n' => $entry->reports_count]) }}</span>
                                        @if($entry->total_bounty > 0)
                                            <span class="bg-gray-200 px-2 py-0.5 rounded">💰 ${{ number_format($entry->total_bounty, 0) }}</span>
                                        @endif
                                    </div>
                                    <span class="rank-badge rank-{{ $entry->rank }}">{{ \App\Models\BugBountyHallOfFame::rankLabel($entry->rank) }}</span>
                                    @if($entry->bio)
                                        <p class="text-sm text-gray-500 mt-2">{{ $entry->bio }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
</div>

@include('public.partials.footer')
</body>
</html>
