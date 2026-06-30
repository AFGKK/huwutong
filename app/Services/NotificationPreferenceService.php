<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationPreferenceService
{
    /**
     * 获取用户的扁平化偏好列表
     * 返回 { preferences: [...], general: {...} }
     */
    public function getPreferences(User $user): array
    {
        $pref = NotificationPreference::where('user_id', $user->id)->first();

        $preferences = [];
        foreach (NotificationPreference::CHANNELS as $channel) {
            foreach (NotificationPreference::CATEGORIES as $category => $label) {
                $preferences[] = [
                    'id' => $pref?->id,
                    'channel' => $channel,
                    'category' => $category,
                    'label' => $label,
                    'enabled' => $pref ? $pref->isEnabled($category, $channel) : $this->getDefaultEnabled($channel, $category),
                    'settings' => null,
                ];
            }
        }

        return [
            'preferences' => $preferences,
            'general' => $pref ? [
                'quiet_hours_start' => $pref->quiet_hours_start,
                'quiet_hours_end' => $pref->quiet_hours_end,
                'timezone' => $pref->timezone ?? 'Asia/Shanghai',
                'digest_frequency' => $pref->digest_frequency ?? 'none',
                'in_quiet_hours' => $pref->isInQuietHours(),
                'digest_due' => $pref->isDigestDue(),
            ] : [
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'timezone' => 'Asia/Shanghai',
                'digest_frequency' => 'none',
                'in_quiet_hours' => false,
                'digest_due' => false,
            ],
        ];
    }

    /**
     * 批量更新偏好
     */
    public function updatePreferences(User $user, array $data): array
    {
        $pref = NotificationPreference::firstOrNew(['user_id' => $user->id]);
        $types = $pref->types ?? [];
        $channels = $pref->channels ?? [];

        foreach ($data as $item) {
            if (empty($item['channel']) || empty($item['category'])) continue;

            $channel = $item['channel'];
            $category = $item['category'];

            if (!in_array($channel, NotificationPreference::CHANNELS) ||
                !array_key_exists($category, NotificationPreference::CATEGORIES)) {
                continue;
            }

            $enabled = filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if (!isset($types[$category])) {
                $types[$category] = [];
            }
            $types[$category][$channel] = $enabled;
        }

        $pref->types = $types;
        $pref->channels = $channels;
        $pref->save();

        $result = $this->getPreferences($user);
        return [$result['preferences'], $result['general']];
    }

    /**
     * 更新通用设置（免打扰、摘要频率、时区）
     */
    public function updateGeneralSettings(User $user, array $settings): NotificationPreference
    {
        $pref = NotificationPreference::firstOrNew(['user_id' => $user->id]);

        if (array_key_exists('quiet_hours_start', $settings)) {
            $pref->quiet_hours_start = $settings['quiet_hours_start'] ?: null;
        }
        if (array_key_exists('quiet_hours_end', $settings)) {
            $pref->quiet_hours_end = $settings['quiet_hours_end'] ?: null;
        }
        if (array_key_exists('timezone', $settings)) {
            $pref->timezone = $settings['timezone'];
        }
        if (array_key_exists('digest_frequency', $settings)) {
            $freq = $settings['digest_frequency'];
            $pref->digest_frequency = in_array($freq, NotificationPreference::DIGEST_FREQUENCIES) ? $freq : 'none';
        }

        // 如果已有 channels/types 则保留
        if (!$pref->exists) {
            $types = [];
            foreach (NotificationPreference::CATEGORIES as $category => $label) {
                $types[$category] = [];
                foreach (NotificationPreference::CHANNELS as $channel) {
                    $types[$category][$channel] = $this->getDefaultEnabled($channel, $category);
                }
            }
            $pref->types = $types;
            $pref->channels = [
                'mail' => true,
                'sms' => !empty($user->phone),
                'database' => true,
            ];
        }

        $pref->save();
        return $pref;
    }

    /**
     * 初始化默认偏好
     */
    public function initializeDefaults(User $user): void
    {
        if (NotificationPreference::where('user_id', $user->id)->exists()) {
            return;
        }

        $types = [];
        foreach (NotificationPreference::CATEGORIES as $category => $label) {
            $types[$category] = [];
            foreach (NotificationPreference::CHANNELS as $channel) {
                $types[$category][$channel] = $this->getDefaultEnabled($channel, $category);
            }
        }

        NotificationPreference::create([
            'user_id' => $user->id,
            'channels' => [
                'mail' => true,
                'sms' => !empty($user->phone),
                'database' => true,
            ],
            'types' => $types,
            'timezone' => 'Asia/Shanghai',
            'digest_frequency' => 'none',
        ]);
    }

    /**
     * 检查用户是否允许接收特定通知
     * 综合判断：偏好设置 + 免打扰时段 + 渠道可用性
     */
    public function shouldNotify(User $user, string $channel, string $category): bool
    {
        $pref = NotificationPreference::where('user_id', $user->id)->first();

        // 1. 检查分类-渠道偏好
        if ($pref) {
            if (!$pref->isEnabled($category, $channel)) {
                return false;
            }
            // 2. 检查免打扰时段（安全类通知不受DND限制）
            if ($category !== 'security' && $pref->isInQuietHours()) {
                return false;
            }
        } else {
            if (!$this->getDefaultEnabled($channel, $category)) {
                return false;
            }
        }

        // 3. 检查渠道可用性
        if ($channel === 'mail' && empty($user->email)) return false;
        if ($channel === 'sms' && empty($user->phone)) return false;

        return true;
    }

    /**
     * 解析某个事件分类应发送到哪些渠道
     * 用于通知系统的统一路由
     * @return string[] 渠道列表 ['mail', 'database']
     */
    public function resolveChannels(User $user, string $category): array
    {
        $channels = [];
        foreach (NotificationPreference::CHANNELS as $channel) {
            if ($this->shouldNotify($user, $channel, $category)) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    /**
     * 获取默认启用状态
     */
    protected function getDefaultEnabled(string $channel, string $category): bool
    {
        if ($category === 'security') return true;
        if ($category === 'promotion' && $channel !== 'database') return false;
        return true;
    }

    /**
     * 获取用户的可用通知渠道
     */
    public function getAvailableChannels(User $user): array
    {
        $channels = [];

        if ($user->email) {
            $channels[] = [
                'channel' => 'mail',
                'label' => '邮件',
                'description' => $user->email,
                'verified' => $user->email_verified_at !== null,
            ];
        }

        if ($user->phone) {
            $channels[] = [
                'channel' => 'sms',
                'label' => '短信',
                'description' => substr_replace($user->phone, '****', 3, 4),
                'verified' => $user->phone_verified_at !== null,
            ];
        }

        $channels[] = [
            'channel' => 'database',
            'label' => '站内信',
            'description' => '平台内消息中心',
            'verified' => true,
        ];

        return $channels;
    }

    /**
     * 管理员: 获取带筛选的偏好列表
     * 注意: channel/category/enabled 存储在 JSON 中，需特殊处理
     */
    public function adminList(array $filters, int $perPage = 15)
    {
        $query = NotificationPreference::with('user:id,name,email');

        // JSON 字段筛选：按渠道筛选
        if (!empty($filters['channel'])) {
            $channel = $filters['channel'];
            $query->where(function (Builder $q) use ($channel) {
                $q->whereRaw("JSON_EXTRACT(`types`, '$.\"$.\"{$channel}\"') IS NOT NULL");
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 统计数据
     */
    public function getStats(): array
    {
        $totalUsers = User::count();
        $usersWithPreferences = NotificationPreference::count();

        $channelEnabled = ['mail' => 0, 'sms' => 0, 'database' => 0];
        $channelTotal = ['mail' => 0, 'sms' => 0, 'database' => 0];
        $withDnd = 0;

        NotificationPreference::chunk(100, function ($prefs) use (&$channelEnabled, &$channelTotal, &$withDnd) {
            foreach ($prefs as $pref) {
                $types = $pref->types ?? [];
                // 有免打扰设置
                if (!empty($pref->quiet_hours_start)) {
                    $withDnd++;
                }
                foreach (NotificationPreference::CATEGORIES as $cat => $label) {
                    foreach (NotificationPreference::CHANNELS as $ch) {
                        $channelTotal[$ch]++;
                        if (isset($types[$cat][$ch]) && $types[$cat][$ch]) {
                            $channelEnabled[$ch]++;
                        }
                    }
                }
            }
        });

        $channelStats = [];
        foreach (NotificationPreference::CHANNELS as $ch) {
            $channelStats[$ch] = [
                'total' => $channelTotal[$ch],
                'enabled' => $channelEnabled[$ch],
                'disabled' => $channelTotal[$ch] - $channelEnabled[$ch],
            ];
        }

        $digestStats = [];
        foreach (NotificationPreference::DIGEST_FREQUENCIES as $freq) {
            $digestStats[$freq] = NotificationPreference::where('digest_frequency', $freq)->count();
        }

        return [
            'total_users' => $totalUsers,
            'users_with_preferences' => $usersWithPreferences,
            'coverage_percentage' => $totalUsers > 0 ? round($usersWithPreferences / $totalUsers * 100, 1) : 0,
            'channels' => $channelStats,
            'with_dnd' => $withDnd,
            'digest_stats' => $digestStats,
        ];
    }

    /**
     * 管理员: 批量操作用户偏好（设为统一值）
     * 例如批量启用/禁用某个分类的某个渠道
     */
    public function batchUpdate(array $userIds, string $channel, string $category, bool $enabled): int
    {
        $count = 0;
        $prefs = NotificationPreference::whereIn('user_id', $userIds)->get();

        foreach ($prefs as $pref) {
            $types = $pref->types ?? [];
            if (!isset($types[$category])) {
                $types[$category] = [];
            }
            $types[$category][$channel] = $enabled;
            $pref->types = $types;
            $pref->save();
            $count++;
        }

        return $count;
    }

    /**
     * 标记摘要已发送
     */
    public function markDigestSent(User $user): void
    {
        $pref = NotificationPreference::where('user_id', $user->id)->first();
        if ($pref) {
            $pref->last_digest_sent_at = now();
            $pref->save();
        }
    }

    /**
     * 获取需要发送摘要的用户列表
     */
    public function getUsersNeedingDigest(): \Illuminate\Support\Collection
    {
        return NotificationPreference::whereIn('digest_frequency', ['daily', 'weekly', 'monthly'])
            ->where(function ($q) {
                $q->whereNull('last_digest_sent_at')
                  ->orWhere('digest_frequency', 'daily')
                  ->orWhere('digest_frequency', 'weekly')
                  ->orWhere('digest_frequency', 'monthly');
            })
            ->with('user')
            ->get()
            ->filter(fn($pref) => $pref->isDigestDue());
    }
}
