<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] ?? __('app.compare_page.title') }}</title>
    <meta name="description" content="{{ $seo['description'] ?? __('app.compare_page.meta_desc') }}">
    @if(!empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    <meta property="og:title" content="{{ $seo['title'] ?? __('app.compare_page.title') }}">
    <meta property="og:description" content="{{ $seo['description'] ?? __('app.compare_page.meta_desc') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/compare') }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        html { scroll-behavior: smooth; }
        .compare-hero-bg {
            background: linear-gradient(
                145deg,
                color-mix(in srgb, var(--pg-primary) 88%, #020617) 0%,
                var(--pg-primary) 48%,
                color-mix(in srgb, var(--pg-primary) 65%, #334155) 100%
            );
        }
        .compare-table th { position: sticky; top: 64px; background: #fff; z-index: 10; }
        .compare-table td, .compare-table th { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; }
        .compare-table tr:hover td { background: #fafafa; }
        .badge-yes { background: #dcfce7; color: #16a34a; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .badge-no { background: #fef2f2; color: #dc2626; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    @include('public.partials.nav')

    <section class="pt-28 pb-12 relative overflow-hidden">
        <div class="absolute inset-0 compare-hero-bg" aria-hidden="true"></div>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative">
            <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-4 tracking-tight">{{ $seo['title'] ?? __('app.compare_page.title') }}</h1>
            <p class="text-lg text-white/80 max-w-3xl mx-auto">{{ $seo['description'] ?? __('app.compare_page.subtitle') }}</p>
        </div>
    </section>

    <section class="py-8 bg-white border-b border-slate-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($competitors as $key => $comp)
                <div class="border border-slate-200 rounded-xl p-5 text-center hover:shadow-md transition">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center text-lg font-bold text-slate-500">{{ strtoupper(substr($comp['name'], 0, 2)) }}</div>
                    <h3 class="font-semibold text-slate-900">{{ $comp['name'] }}</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ $comp['description'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="compare-table w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b-2 border-slate-200">
                            <th class="text-left font-semibold text-slate-700 min-w-[140px]">{{ __('app.compare_page.dimension') }}</th>
                            <th class="text-center font-bold text-slate-800 min-w-[120px]">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="w-5 h-5 rounded bg-slate-900 text-white text-xs flex items-center justify-center">H</span>
                                    {{ site_setting('site_name', __('app.app_name')) }}
                                </div>
                            </th>
                            @foreach($competitors as $key => $comp)
                            <th class="text-center font-medium text-slate-600 min-w-[120px]">{{ $comp['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $competitorKeys = array_keys($competitors);
                            $comparisonData = $comparison_data;
                        @endphp
                        @foreach($dimensions as $dimKey => $dim)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="font-medium text-slate-700">{{ $dim['label'] }}</td>
                            @foreach(array_merge(['huwutong'], $competitorKeys) as $compKey)
                            @php
                                $val = $comparisonData[$dimKey][$compKey] ?? '—';
                                $isHuwutong = $compKey === 'huwutong';
                            @endphp
                            <td class="text-center {{ $isHuwutong ? 'bg-slate-50' : '' }}">
                                @if($val === true)
                                    <span class="badge-yes">{{ __('app.compare_page.yes') }}</span>
                                @elseif($val === false)
                                    <span class="badge-no">{{ __('app.compare_page.no') }}</span>
                                @else
                                    <span class="text-slate-700 text-xs">{{ $val }}</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @php
                $scores = [];
                foreach (array_merge(['huwutong'], $competitorKeys) as $compKey) {
                    $score = 0;
                    foreach ($dimensions as $dimKey => $dim) {
                        $val = $comparisonData[$dimKey][$compKey] ?? null;
                        if ($val === true) {
                            $score++;
                        }
                    }
                    $scores[$compKey] = $score;
                }
                arsort($scores);
                $topScore = reset($scores);
            @endphp
            <div class="mt-8 grid grid-cols-1 md:grid-cols-5 gap-3">
                @foreach($scores as $compKey => $score)
                @php $comp = $competitors[$compKey] ?? ['name' => site_setting('site_name', __('app.app_name')), 'description' => '']; @endphp
                <div class="border rounded-xl p-4 text-center {{ $compKey === 'huwutong' ? 'border-slate-800 bg-slate-50 ring-2 ring-slate-200' : 'border-slate-200' }}">
                    @if($compKey === 'huwutong')
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-slate-900 text-white font-bold flex items-center justify-center">H</div>
                    @else
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-slate-100 text-slate-400 font-bold flex items-center justify-center">{{ strtoupper(substr($comp['name'], 0, 2)) }}</div>
                    @endif
                    <div class="text-lg font-bold {{ $compKey === 'huwutong' ? 'text-slate-800' : 'text-slate-700' }}">{{ $score }}/{{ count($dimensions) }}</div>
                    <div class="text-xs {{ $compKey === 'huwutong' ? 'text-slate-600' : 'text-slate-500' }}">{{ $comp['name'] }}</div>
                    @if($score === $topScore && $compKey === 'huwutong')
                        <div class="mt-1 text-xs font-semibold text-emerald-700">{{ __('app.compare_page.leading') }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-12 bg-white border-t border-slate-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-8 tracking-tight text-slate-900">{{ __('app.compare_page.why_title') }}</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="p-6 rounded-xl border border-slate-100 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-semibold mb-2 text-slate-900">{{ __('app.compare_page.why_1_title') }}</h3>
                    <p class="text-sm text-slate-500">{{ __('app.compare_page.why_1_desc') }}</p>
                </div>
                <div class="p-6 rounded-xl border border-slate-100 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold mb-2 text-slate-900">{{ __('app.compare_page.why_2_title') }}</h3>
                    <p class="text-sm text-slate-500">{{ __('app.compare_page.why_2_desc') }}</p>
                </div>
                <div class="p-6 rounded-xl border border-slate-100 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    </div>
                    <h3 class="font-semibold mb-2 text-slate-900">{{ __('app.compare_page.why_3_title') }}</h3>
                    <p class="text-sm text-slate-500">{{ __('app.compare_page.why_3_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-slate-900">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4 tracking-tight">{{ __('app.compare_page.cta_title') }}</h2>
            <p class="text-slate-300 mb-6">{{ __('app.compare_page.cta_subtitle') }}</p>
            <a href="/build/register" class="inline-block bg-white text-slate-900 px-8 py-3 rounded-xl font-bold hover:bg-slate-100 transition shadow-lg">{{ __('app.compare_page.cta_button') }}</a>
        </div>
    </section>

    @include('public.partials.footer')
</body>
</html>
