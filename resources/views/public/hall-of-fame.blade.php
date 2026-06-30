<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安全致谢 - 互物通 Hall of Fame</title>
    <meta name="robots" content="all">
    <meta name="description" content="互物通安全漏洞致谢列表 — 感谢以下安全研究人员为我们的安全做出的贡献">
    @vite('resources/css/public.css')
    <style>
        .hall-of-fame-page { min-height: 80vh; padding-top: 100px; padding-bottom: 60px; }
        .hacker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; margin: 1.5rem 0; }
        .hacker-card { background: #f8f9fa; border-radius: 10px; padding: 1.25rem; text-align: center; border: 1px solid #e9ecef; transition: transform .15s, box-shadow .15s; }
        .hacker-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
        .hacker-card .hacker-avatar { width: 64px; height: 64px; border-radius: 50%; background: #e74c3c; color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto .75rem; font-size: 1.5rem; font-weight: 700; }
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

<div class="hall-of-fame-page">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-1">🏆 安全致谢</h1>
            <p class="text-gray-500 mb-8">感谢以下安全研究人员为互物通安全做出的贡献</p>

            @if($hallOfFame->isEmpty())
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🛡️</div>
                    <p class="text-gray-400 text-lg">暂无记录，期待您成为第一位！</p>
                    <p class="mt-3"><a href="/security-policy" class="text-primary-600 hover:text-primary-700 font-medium">了解如何参与漏洞奖励计划 →</a></p>
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
                                        <span class="bg-gray-200 px-2 py-0.5 rounded">📄 {{ $entry->reports_count }} 报告</span>
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
