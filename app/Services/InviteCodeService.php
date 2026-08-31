<?php

namespace App\Services;

use App\Models\InviteChannel;
use App\Models\InviteChannelDailyStat;
use App\Models\InviteCode;
use App\Models\RegistrationPortalConfig;
use App\Models\RegistrationTracking;
use Illuminate\Support\Str;

/**
 * 邀请码系统增强服务
 *
 * 提供：
 * - 渠道管理（CRUD + 统计）
 * - 渠道分组批量生成邀请码
 * - 注册追踪（来源/UTM/落地页）
 * - 自助注册门户配置
 * - 渠道统计看板
 */
class InviteCodeService
{
    // ─── 渠道管理 ───

    public function getChannels(array $filters = []): array
    {
        $query = InviteChannel::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate(min((int) ($filters['per_page'] ?? 50), 100))
            ->toArray();
    }

    public function getChannel(int $id): InviteChannel
    {
        return InviteChannel::withCount('inviteCodes')->findOrFail($id);
    }

    public function createChannel(array $data): InviteChannel
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(4);
        return InviteChannel::create($data);
    }

    public function updateChannel(InviteChannel $channel, array $data): InviteChannel
    {
        $channel->update($data);
        return $channel->fresh();
    }

    public function deleteChannel(InviteChannel $channel): void
    {
        $channel->inviteCodes()->update(['channel_id' => null]);
        $channel->dailyStats()->delete();
        $channel->delete();
    }

    // ─── 邀请码增强 ───

    public function getInviteCodes(array $filters = []): array
    {
        $query = InviteCode::with('channel');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }
        if (!empty($filters['search'])) {
            $q = $filters['search'];
            $query->where('code', 'like', "%{$q}%");
        }

        return $query->orderByDesc('id')
            ->paginate(min((int) ($filters['per_page'] ?? 50), 100))
            ->toArray();
    }

    public function generateInviteCodes(int $count, array $options = []): array
    {
        $codes = [];
        $channelId = $options['channel_id'] ?? null;

        for ($i = 0; $i < $count; $i++) {
            $code = InviteCode::create([
                'channel_id' => $channelId,
                'code' => InviteCode::generateCode(),
                'max_uses' => $options['max_uses'] ?? 1,
                'expires_at' => !empty($options['expires_at']) ? now()->parse($options['expires_at']) : null,
                'status' => 'active',
                'remarks' => $options['remarks'] ?? null,
                'meta' => $options['meta'] ?? null,
                'created_by_email' => $options['created_by_email'] ?? null,
            ]);
            $codes[] = $code;
        }

        // 更新渠道统计
        if ($channelId) {
            InviteChannel::where('id', $channelId)->increment('code_count', $count);
        }

        return $codes;
    }

    public function disableInviteCode(InviteCode $code): void
    {
        $code->update(['status' => 'disabled']);
    }

    public function getInviteCodeStats(): array
    {
        return [
            'total' => InviteCode::count(),
            'active' => InviteCode::where('status', 'active')->count(),
            'exhausted' => InviteCode::where('status', 'active')
                ->where('max_uses', '>', 0)
                ->whereColumn('used_count', '>=', 'max_uses')
                ->count(),
            'expired' => InviteCode::where('status', 'expired')
                ->orWhere(function ($q) {
                    $q->where('expires_at', '<', now())->where('status', 'active');
                })->count(),
            'disabled' => InviteCode::where('status', 'disabled')->count(),
            'total_uses' => InviteCode::sum('used_count'),
            'by_channel' => InviteCode::selectRaw('channel_id, COUNT(*) as cnt')
                ->groupBy('channel_id')->get()->pluck('cnt', 'channel_id')->toArray(),
        ];
    }

    public function validateInviteCode(string $code): ?array
    {
        $invite = InviteCode::with('channel')
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (!$invite) {
            return ['valid' => false, 'reason' => 'not_found', 'message' => __('app.common.invite_code_not_found')];
        }

        if ($invite->status !== 'active') {
            return ['valid' => false, 'reason' => 'disabled', 'message' => __('app.common.invite_code_disabled')];
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'expired', 'message' => __('app.common.invite_code_expired')];
        }

        if ($invite->max_uses > 0 && $invite->used_count >= $invite->max_uses) {
            return ['valid' => false, 'reason' => 'exhausted', 'message' => __('app.common.invite_code_exhausted')];
        }

        return [
            'valid' => true,
            'code' => $invite->code,
            'channel' => $invite->channel?->only(['id', 'name', 'slug']),
            'channel_name' => $invite->channel?->name,
            'remarks' => $invite->remarks,
        ];
    }

    public function consumeInviteCode(string $code): bool
    {
        $invite = InviteCode::where('code', strtoupper(trim($code)))->first();
        if (!$invite || !$invite->isValid()) {
            return false;
        }
        return $invite->consume();
    }

    // ─── 注册追踪 ───

    public function trackRegistration(array $data): RegistrationTracking
    {
        return RegistrationTracking::create([
            'user_id' => $data['user_id'] ?? null,
            'invite_code' => $data['invite_code'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
            'source' => $data['source'] ?? 'direct',
            'referrer_url' => $data['referrer_url'] ?? null,
            'landing_page' => $data['landing_page'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'utm_params' => $data['utm_params'] ?? null,
        ]);
    }

    public function markConverted(int $registrationId, string $conversionType = 'subscription'): void
    {
        RegistrationTracking::where('id', $registrationId)->update([
            'converted' => true,
            'converted_at' => now(),
            'conversion_type' => $conversionType,
        ]);
    }

    public function getRegistrationTrackings(array $filters = []): array
    {
        $query = RegistrationTracking::with(['user', 'channel']);

        if (!empty($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['converted'])) {
            $query->where('converted', $filters['converted'] === 'yes');
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
            ->paginate(min((int) ($filters['per_page'] ?? 50), 100))
            ->toArray();
    }

    // ─── 注册门户配置 ───

    public function getPortalConfig(): array
    {
        return [
            'enabled' => RegistrationPortalConfig::getValue('portal_enabled', ['value' => false]),
            'title' => RegistrationPortalConfig::getValue('portal_title', ['value' => '创建您的账户']),
            'subtitle' => RegistrationPortalConfig::getValue('portal_subtitle', ['value' => '请使用邀请码注册']),
            'brand_name' => RegistrationPortalConfig::getValue('portal_brand_name', ['value' => config('app.name')]),
            'logo_url' => RegistrationPortalConfig::getValue('portal_logo_url', ['value' => null]),
            'require_invite' => RegistrationPortalConfig::getValue('require_invite', ['value' => true]),
            'require_email_verify' => RegistrationPortalConfig::getValue('require_email_verify', ['value' => false]),
            'accept_terms' => RegistrationPortalConfig::getValue('accept_terms', ['value' => true]),
            'terms_url' => RegistrationPortalConfig::getValue('terms_url', ['value' => '/terms']),
            'privacy_url' => RegistrationPortalConfig::getValue('privacy_url', ['value' => '/privacy']),
            'allowed_domains' => RegistrationPortalConfig::getValue('allowed_domains', ['value' => []]),
            'custom_css' => RegistrationPortalConfig::getValue('custom_css', ['value' => null]),
            'custom_html' => RegistrationPortalConfig::getValue('custom_html', ['value' => null]),
            'features' => RegistrationPortalConfig::getValue('portal_features', ['value' => [
                ['icon' => 'Key', 'title' => '邀请码保护', 'desc' => '仅限受邀用户注册'],
                ['icon' => 'Lock', 'title' => '安全加密', 'desc' => '数据传输加密保护'],
                ['icon' => 'Bell', 'title' => '实时通知', 'desc' => '注册成功即时通知'],
            ]]),
        ];
    }

    public function updatePortalConfig(array $config): array
    {
        foreach ($config as $key => $value) {
            $dbKey = match ($key) {
                'enabled' => 'portal_enabled',
                'title' => 'portal_title',
                'subtitle' => 'portal_subtitle',
                'brand_name' => 'portal_brand_name',
                'logo_url' => 'portal_logo_url',
                'require_invite' => 'require_invite',
                'require_email_verify' => 'require_email_verify',
                'accept_terms' => 'accept_terms',
                'terms_url' => 'terms_url',
                'privacy_url' => 'privacy_url',
                'allowed_domains' => 'allowed_domains',
                'custom_css' => 'custom_css',
                'custom_html' => 'custom_html',
                'features' => 'portal_features',
                default => null,
            };
            if ($dbKey) {
                RegistrationPortalConfig::setValue($dbKey, ['value' => $value]);
            }
        }

        return $this->getPortalConfig();
    }

    // ─── 渠道统计看板 ───

    public function getChannelDashboard(int $channelId): array
    {
        $channel = InviteChannel::findOrFail($channelId);

        $dailyStats = InviteChannelDailyStat::where('channel_id', $channelId)
            ->orderBy('stat_date', 'desc')
            ->limit(30)
            ->get();

        $registrations = RegistrationTracking::where('channel_id', $channelId)->count();
        $converted = RegistrationTracking::where('channel_id', $channelId)->where('converted', true)->count();
        $todayReg = RegistrationTracking::where('channel_id', $channelId)
            ->whereDate('created_at', today())->count();
        $totalCodes = InviteCode::where('channel_id', $channelId)->count();
        $usedCodes = InviteCode::where('channel_id', $channelId)->sum('used_count');

        return [
            'channel' => $channel,
            'stats' => [
                'total_registrations' => $registrations,
                'converted' => $converted,
                'conversion_rate' => $registrations > 0 ? round(($converted / $registrations) * 100, 2) : 0,
                'today_registrations' => $todayReg,
                'total_codes' => $totalCodes,
                'used_codes' => $usedCodes,
                'code_usage_rate' => $totalCodes > 0 ? round(($usedCodes / $totalCodes) * 100, 2) : 0,
            ],
            'daily_stats' => $dailyStats,
        ];
    }

    public function getOverallDashboard(): array
    {
        $totalChannels = InviteChannel::count();
        $activeChannels = InviteChannel::where('status', 'active')->count();
        $totalRegistrations = RegistrationTracking::count();
        $totalConverted = RegistrationTracking::where('converted', true)->count();
        $todayRegistrations = RegistrationTracking::whereDate('created_at', today())->count();
        $totalCodes = InviteCode::count();
        $totalUses = InviteCode::sum('used_count');

        $bySource = RegistrationTracking::selectRaw('source, COUNT(*) as cnt')
            ->groupBy('source')->get()->pluck('cnt', 'source')->toArray();

        $recentRegistrations = RegistrationTracking::with('channel')
            ->orderByDesc('created_at')->limit(10)->get();

        return [
            'stats' => [
                'total_channels' => $totalChannels,
                'active_channels' => $activeChannels,
                'total_registrations' => $totalRegistrations,
                'total_converted' => $totalConverted,
                'overall_conversion_rate' => $totalRegistrations > 0
                    ? round(($totalConverted / $totalRegistrations) * 100, 2) : 0,
                'today_registrations' => $todayRegistrations,
                'total_codes' => $totalCodes,
                'total_uses' => $totalUses,
            ],
            'by_source' => $bySource,
            'recent_registrations' => $recentRegistrations,
        ];
    }

    // ─── 统计日志（用于 cron） ───
    public function recordDailyStats(): void
    {
        $channels = InviteChannel::all();
        $today = today();

        foreach ($channels as $channel) {
            $registrations = RegistrationTracking::where('channel_id', $channel->id)
                ->whereDate('created_at', $today)->count();
            $conversions = RegistrationTracking::where('channel_id', $channel->id)
                ->where('converted', true)
                ->whereDate('converted_at', $today)->count();

            InviteChannelDailyStat::updateOrCreate(
                ['channel_id' => $channel->id, 'stat_date' => $today],
                [
                    'registrations' => $registrations,
                    'conversions' => $conversions,
                ]
            );
        }
    }
}
