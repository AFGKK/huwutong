<?php

namespace App\Services;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppInstallation;
use App\Models\MarketplaceAppReviewLog;
use App\Models\MarketplaceAppVersion;
use App\Models\MarketplaceDeveloper;
use App\Models\MarketplaceDownloadLog;
use App\Models\EarningsAccount;
use App\Models\PlatformFee;
use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 开放平台 / 应用市场服务 (M3-28)
 */
class OpenPlatformService
{
    const CATEGORIES = ['integration', 'automation', 'analytics', 'security', 'billing', 'other'];

    const APP_STATUSES = ['draft', 'pending_review', 'published', 'rejected', 'suspended'];

    public function getStats(): array
    {
        return [
            'total_developers' => MarketplaceDeveloper::count(),
            'active_developers' => MarketplaceDeveloper::where('status', 'active')->count(),
            'pending_developers' => MarketplaceDeveloper::where('status', 'pending')->count(),
            'total_apps' => MarketplaceApp::count(),
            'published_apps' => MarketplaceApp::where('status', 'published')->count(),
            'pending_review_apps' => MarketplaceApp::where('status', 'pending_review')->count(),
            'total_installations' => MarketplaceAppInstallation::where('status', 'active')->count(),
        ];
    }

    public function listDevelopers(array $filters = [], int $perPage = 20)
    {
        $query = MarketplaceDeveloper::with('user:id,name,email')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function registerDeveloper(User $user, array $data): MarketplaceDeveloper
    {
        if (MarketplaceDeveloper::where('user_id', $user->id)->exists()) {
            throw new \RuntimeException(__('app.open_platform.already_registered'));
        }

        return MarketplaceDeveloper::create([
            'user_id' => $user->id,
            'display_name' => $data['display_name'],
            'company_name' => $data['company_name'] ?? null,
            'website' => $data['website'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function verifyDeveloper(MarketplaceDeveloper $developer, User $reviewer, string $action, ?string $notes = null): MarketplaceDeveloper
    {
        if (!in_array($action, ['approve', 'suspend'])) {
            throw new \RuntimeException(__('app.open_platform.invalid_action'));
        }

        $developer->update([
            'status' => $action === 'approve' ? 'active' : 'suspended',
            'verified_at' => $action === 'approve' ? now() : null,
            'verified_by' => $action === 'approve' ? $reviewer->id : null,
        ]);

        return $developer->fresh()->load('user:id,name,email');
    }

    public function getDeveloperForUser(User $user): ?MarketplaceDeveloper
    {
        return MarketplaceDeveloper::where('user_id', $user->id)->first();
    }

    public function listApps(array $filters = [], int $perPage = 20)
    {
        $query = MarketplaceApp::with(['developer.user:id,name,email'])
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['developer_id'])) {
            $query->where('developer_id', $filters['developer_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function listMarketplaceApps(array $filters = [], int $perPage = 20)
    {
        $filters['status'] = 'published';
        return $this->listApps($filters, $perPage);
    }

    public function createApp(MarketplaceDeveloper $developer, array $data): MarketplaceApp
    {
        if (!$developer->isActive()) {
            throw new \RuntimeException(__('app.open_platform.developer_inactive'));
        }

        $slug = Str::slug($data['slug'] ?? $data['name']);
        if (MarketplaceApp::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        return DB::transaction(function () use ($developer, $data, $slug) {
            $app = MarketplaceApp::create([
                'developer_id' => $developer->id,
                'slug' => $slug,
                'name' => $data['name'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'integration',
                'icon_url' => $data['icon_url'] ?? null,
                'pricing_type' => $data['pricing_type'] ?? 'free',
                'price' => $data['price'] ?? 0,
                'webhook_url' => $data['webhook_url'] ?? null,
                'permissions' => $data['permissions'] ?? [],
                'documentation_url' => $data['documentation_url'] ?? null,
                'repository_url' => $data['repository_url'] ?? null,
                'status' => 'draft',
            ]);

            if (!empty($data['version'])) {
                $this->addVersion($app, [
                    'version' => $data['version'],
                    'changelog' => $data['changelog'] ?? 'Initial release',
                    'package_url' => $data['package_url'] ?? null,
                ]);
            }

            return $app->fresh()->load('developer');
        });
    }

    public function updateApp(MarketplaceApp $app, array $data): MarketplaceApp
    {
        if (!in_array($app->status, ['draft', 'rejected'])) {
            throw new \RuntimeException(__('app.open_platform.app_not_editable'));
        }

        $app->update(collect($data)->only([
            'name', 'short_description', 'description', 'category', 'icon_url',
            'pricing_type', 'price', 'webhook_url', 'permissions',
            'documentation_url', 'repository_url',
        ])->toArray());

        return $app->fresh();
    }

    public function submitForReview(MarketplaceApp $app): MarketplaceApp
    {
        if ($app->status !== 'draft' && $app->status !== 'rejected') {
            throw new \RuntimeException(__('app.open_platform.cannot_submit_review'));
        }

        if (!$app->current_version) {
            throw new \RuntimeException(__('app.open_platform.version_required'));
        }

        $app->update(['status' => 'pending_review', 'review_notes' => null]);

        $this->logReview($app, null, 'submit', __('app.open_platform.review_submit'));

        return $app->fresh();
    }

    public function reviewApp(MarketplaceApp $app, User $reviewer, string $action, ?string $notes = null): MarketplaceApp
    {
        $statusMap = [
            'approve' => 'published',
            'reject' => 'rejected',
            'request_changes' => 'draft',
            'suspend' => 'suspended',
        ];

        if (!isset($statusMap[$action])) {
            throw new \RuntimeException(__('app.open_platform.invalid_review_action'));
        }

        if ($action === 'approve' && $app->status !== 'pending_review') {
            throw new \RuntimeException(__('app.open_platform.only_pending_can_approve'));
        }

        $app->update([
            'status' => $statusMap[$action],
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'published_at' => $action === 'approve' ? now() : $app->published_at,
        ]);

        $this->logReview($app, $reviewer, $action, $notes);

        // ── 紧急下架：通知所有已安装用户 + 标记安装记录 ──
        if ($action === 'suspend') {
            $this->notifyInstalledUsersOnSuspend($app, $notes);
        }

        // ── 恢复上架：同步恢复安装记录 ──
        if ($action === 'approve' && $app->getOriginal('status') === 'suspended') {
            MarketplaceAppInstallation::where('app_id', $app->id)
                ->where('status', 'suspended')
                ->update(['status' => 'active']);
        }

        return $app->fresh()->load(['developer.user:id,name,email', 'reviewer:id,name']);
    }

    /**
     * 应用被下架时通知所有已安装用户并标记安装记录。
     */
    protected function notifyInstalledUsersOnSuspend(MarketplaceApp $app, ?string $reason): void
    {
        $installations = MarketplaceAppInstallation::where('app_id', $app->id)
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get();

        if ($installations->isEmpty()) {
            return;
        }

        $reasonText = $reason ? "原因：{$reason}" : __('app.open_platform.suspend_fallback_reason');

        // 批量标记安装记录为 suspended
        MarketplaceAppInstallation::where('app_id', $app->id)
            ->where('status', 'active')
            ->update(['status' => 'suspended']);

        // 通过 NotificationService 发送站内信
        $notificationService = app(\App\Services\NotificationService::class);
        foreach ($installations as $inst) {
            if ($inst->user) {
                $notificationService->send(
                    $inst->user,
                    'app_suspended',
                    __('app.open_platform.app_suspended_title', ['name' => $app->name]),
                    __('app.open_platform.app_suspended_body', ['name' => $app->name, 'reason' => $reasonText]),
                    [
                        'app_id' => $app->id,
                        'app_name' => $app->name,
                        'severity' => 'critical',
                        'require_ack' => true,
                    ]
                );
            }
        }
    }

    /**
     * 向所有已安装用户推送强制更新通知。
     */
    public function forceUpdateNotification(MarketplaceApp $app, string $reason, ?string $targetVersion = null): int
    {
        $installations = MarketplaceAppInstallation::where('app_id', $app->id)
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get();

        if ($installations->isEmpty()) {
            return 0;
        }

        $versionText = $targetVersion ? __('app.open_platform.upgrade_to_version', ['version' => $targetVersion]) : __('app.open_platform.upgrade_to_latest');
        $notificationService = app(\App\Services\NotificationService::class);
        $count = 0;

        foreach ($installations as $inst) {
            if ($inst->user) {
                $notificationService->send(
                    $inst->user,
                    'app_force_update',
                    __('app.open_platform.force_update_title', ['name' => $app->name]),
                    __('app.open_platform.force_update_body', ['name' => $app->name, 'reason' => $reason, 'version_text' => $versionText]),
                    [
                        'app_id' => $app->id,
                        'app_name' => $app->name,
                        'target_version' => $targetVersion,
                        'severity' => 'critical',
                        'require_ack' => true,
                        'force_update' => true,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    public function addVersion(MarketplaceApp $app, array $data): MarketplaceAppVersion
    {
        $version = MarketplaceAppVersion::updateOrCreate(
            ['app_id' => $app->id, 'version' => $data['version']],
            [
                'changelog' => $data['changelog'] ?? null,
                'package_url' => $data['package_url'] ?? null,
                'min_platform_version' => $data['min_platform_version'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'released_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
            ]
        );

        $app->update(['current_version' => $data['version']]);

        return $version;
    }

    public function installApp(MarketplaceApp $app, User $user, ?int $tenantId = null, array $config = []): MarketplaceAppInstallation
    {
        if (!$app->isPublished()) {
            throw new \RuntimeException(__('app.open_platform.app_not_published'));
        }

        $existing = MarketplaceAppInstallation::where('app_id', $app->id)
            ->where('user_id', $user->id)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        if ($existing && $existing->status === 'active') {
            throw new \RuntimeException(__('app.open_platform.app_already_installed'));
        }

        return DB::transaction(function () use ($app, $user, $tenantId, $config, $existing) {
            if ($existing) {
                $existing->update([
                    'status' => 'active',
                    'installed_version' => $app->current_version,
                    'config' => $config,
                    'installed_at' => now(),
                    'uninstalled_at' => null,
                ]);
                return $existing->fresh();
            }

            $installation = MarketplaceAppInstallation::create([
                'app_id' => $app->id,
                'tenant_id' => $tenantId ?? $user->tenant_id,
                'user_id' => $user->id,
                'status' => 'active',
                'installed_version' => $app->current_version,
                'config' => $config,
                'installed_at' => now(),
            ]);

            $app->increment('install_count');

            return $installation->load('app');
        });
    }

    public function uninstallApp(MarketplaceAppInstallation $installation): MarketplaceAppInstallation
    {
        if ($installation->status !== 'active') {
            throw new \RuntimeException(__('app.open_platform.app_not_active'));
        }

        $installation->update([
            'status' => 'uninstalled',
            'uninstalled_at' => now(),
        ]);

        return $installation->fresh();
    }

    public function listInstallations(array $filters = [], int $perPage = 20)
    {
        $query = MarketplaceAppInstallation::with(['app:id,name,slug', 'user:id,name,email'])
            ->orderByDesc('installed_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['app_id'])) {
            $query->where('app_id', $filters['app_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->paginate($perPage);
    }

    // ══════════════════════════════════════════
    //  排行榜
    // ══════════════════════════════════════════

    /**
     * 排行榜 - 支持多种排序维度
     */
    public function getRankings(string $type = 'downloads', string $category = null, int $limit = 20): array
    {
        $query = MarketplaceApp::where('status', 'published')
            ->with('developer.user:id,name');

        if ($category) {
            $query->where('category', $category);
        }

        switch ($type) {
            case 'downloads':
                $query->orderByDesc('install_count');
                $label = __('app.open_platform.rank_downloads');
                break;
            case 'trending':
                // 近7天下载量最多的
                $query->orderByDesc('install_count');
                $label = __('app.open_platform.rank_trending');
                break;
            case 'newest':
                $query->orderByDesc('published_at');
                $label = __('app.open_platform.rank_newest');
                break;
            case 'rating':
                $query->where('review_count', '>', 0)->orderByDesc('avg_rating')->orderByDesc('review_count');
                $label = __('app.open_platform.rank_rating');
                break;
            default:
                $query->orderByDesc('install_count');
                $label = __('app.open_platform.rank_hot');
        }

        return [
            'type' => $type,
            'label' => $label,
            'apps' => $query->take($limit)->get(['id', 'name', 'slug', 'icon_url', 'category', 'short_description', 'install_count', 'avg_rating', 'review_count', 'current_version', 'published_at']),
        ];
    }

    // ══════════════════════════════════════════
    //  下载/安装趋势统计
    // ══════════════════════════════════════════

    public function getDownloadTrend(int $days = 30): array
    {
        $since = now()->subDays($days);
        $raw = MarketplaceDownloadLog::where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as date, action, COUNT(*) as total")
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get();

        $trend = [];
        $dateLabels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $dateLabels[] = $d;
            $trend[$d] = ['download' => 0, 'install' => 0, 'view_detail' => 0];
        }

        foreach ($raw as $row) {
            if (isset($trend[$row->date])) {
                $trend[$row->date][$row->action] = (int) $row->total;
            }
        }

        return [
            'labels' => $dateLabels,
            'datasets' => [
                ['name' => __('app.open_platform.chart_view_detail'), 'data' => array_map(fn($d) => $trend[$d]['view_detail'], $dateLabels)],
                ['name' => __('app.open_platform.chart_download'), 'data' => array_map(fn($d) => $trend[$d]['download'], $dateLabels)],
                ['name' => __('app.open_platform.chart_install'), 'data' => array_map(fn($d) => $trend[$d]['install'], $dateLabels)],
            ],
        ];
    }

    public function getAppDownloadTrend(int $appId, int $days = 30): array
    {
        $since = now()->subDays($days);
        $raw = MarketplaceDownloadLog::byApp($appId)
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as date, action, COUNT(*) as total")
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get();

        $trend = [];
        $dateLabels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $dateLabels[] = $d;
            $trend[$d] = ['view_detail' => 0, 'download' => 0, 'install' => 0, 'uninstall' => 0, 'update' => 0];
        }

        foreach ($raw as $row) {
            if (isset($trend[$row->date])) {
                $trend[$row->date][$row->action] = (int) $row->total;
            }
        }

        return [
            'labels' => $dateLabels,
            'datasets' => [
                ['name' => __('app.open_platform.chart_view_detail'), 'data' => array_map(fn($d) => $trend[$d]['view_detail'], $dateLabels)],
                ['name' => __('app.open_platform.chart_download'), 'data' => array_map(fn($d) => $trend[$d]['download'], $dateLabels)],
                ['name' => __('app.open_platform.chart_install'), 'data' => array_map(fn($d) => $trend[$d]['install'], $dateLabels)],
                ['name' => __('app.open_platform.chart_uninstall'), 'data' => array_map(fn($d) => $trend[$d]['uninstall'], $dateLabels)],
            ],
        ];
    }

    // ══════════════════════════════════════════
    //  版本更新检测
    // ══════════════════════════════════════════

    public function checkForUpdate(int $appId, string $currentVersion): ?array
    {
        $app = MarketplaceApp::findOrFail($appId);

        if (!$app->current_version || version_compare($currentVersion, $app->current_version, '>=')) {
            return null; // 已是最新
        }

        $latestVersion = MarketplaceAppVersion::where('app_id', $appId)
            ->where('version', $app->current_version)
            ->first();

        return [
            'app_id' => $appId,
            'app_name' => $app->name,
            'current_version' => $currentVersion,
            'latest_version' => $app->current_version,
            'changelog' => $latestVersion?->changelog,
            'package_url' => $latestVersion?->package_url,
            'released_at' => $latestVersion?->released_at,
        ];
    }

    // ══════════════════════════════════════════
    //  统计摘要
    // ══════════════════════════════════════════

    public function getMarketplaceSummary(): array
    {
        $totalApps = MarketplaceApp::where('status', 'published')->count();
        $totalInstalls = MarketplaceAppInstallation::where('status', 'active')->count();
        $totalDevs = MarketplaceDeveloper::where('status', 'active')->count();

        $categoryDist = MarketplaceApp::where('status', 'published')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $topApps = MarketplaceApp::where('status', 'published')
            ->orderByDesc('install_count')
            ->take(5)
            ->get(['id', 'name', 'install_count', 'avg_rating']);

        return [
            'total_apps' => $totalApps,
            'total_installs' => $totalInstalls,
            'total_developers' => $totalDevs,
            'category_distribution' => $categoryDist,
            'top_apps' => $topApps,
        ];
    }

    protected function logReview(MarketplaceApp $app, ?User $reviewer, string $action, ?string $notes = null): void
    {
        MarketplaceAppReviewLog::create([
            'app_id' => $app->id,
            'reviewer_id' => $reviewer?->id,
            'action' => $action,
            'notes' => $notes,
        ]);
    }

    // ══════════════════════════════════════════
    //  文件上传
    // ══════════════════════════════════════════

    public function uploadPackage(UploadedFile $file): array
    {
        $allowedExts = ['apk', 'ipa', 'appimage', 'zip', '7z', 'tar.gz'];
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, $allowedExts)) {
            throw new \InvalidArgumentException(__('app.open_platform.invalid_file_format'));
        }

        if ($file->getSize() > 500 * 1024 * 1024) {
            throw new \InvalidArgumentException(__('app.open_platform.file_too_large'));
        }

        $path = $file->store('marketplace/packages/' . date('Ymd'), 'public');
        $url = Storage::url($path);

        return [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $ext,
            'path' => $path,
            'url' => $url,
        ];
    }

    public function uploadScreenshot(UploadedFile $file): array
    {
        $path = $file->store('marketplace/screenshots/' . date('Ymd'), 'public');
        $url = Storage::url($path);

        return [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'url' => $url,
            'path' => $path,
        ];
    }

    // ══════════════════════════════════════════
    //  开发者收益
    // ══════════════════════════════════════════

    /**
     * 初始化开发者的收益账户
     */
    public function initEarningsAccount(MarketplaceDeveloper $developer): EarningsAccount
    {
        if ($developer->earnings_account_id) {
            return $developer->earningsAccount;
        }

        return DB::transaction(function () use ($developer) {
            $account = EarningsAccount::create([
                'tenant_id' => $developer->user->tenant_id,
                'user_id' => $developer->user_id,
                'type' => 'developer',
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_withdrawn' => 0,
                'status' => 'active',
            ]);

            $developer->update(['earnings_account_id' => $account->id]);

            return $account;
        });
    }

    /**
     * 为开发者入账收益（付费应用安装/内购时调用）
     */
    public function creditEarnings(MarketplaceApp $app, float $amount, string $description = ''): void
    {
        $developer = $app->developer;

        if (!$developer || !$developer->earnings_account_id) {
            return;
        }

        $platformFee = $amount * (config('open-platform.commission.platform_fee_percentage', 20) / 100);
        $netAmount = $amount - $platformFee;

        DB::transaction(function () use ($developer, $netAmount, $amount, $platformFee, $description, $app) {
            $account = $developer->earningsAccount;
            $account->increment('pending_balance', $netAmount);
            $developer->increment('total_earned', $netAmount);

            // 创建平台费用记录
            PlatformFee::create([
                'tenant_id' => $developer->user->tenant_id,
                'feeable_type' => MarketplaceApp::class,
                'feeable_id' => $app->id,
                'fee_type' => 'commission',
                'name' => $description ?: __('app.open_platform.commission_name', ['name' => $app->name]),
                'amount' => $platformFee,
                'rate' => config('open-platform.commission.platform_fee_percentage', 20) / 100,
                'currency' => 'CNY',
                'status' => 'collected',
                'collected_at' => now(),
            ]);
        });
    }

    /**
     * 开发者收益摘要
     */
    public function getDeveloperEarnings(int $developerId): array
    {
        $dev = MarketplaceDeveloper::with('earningsAccount')->findOrFail($developerId);

        $earningsByApp = MarketplaceApp::where('developer_id', $developerId)
            ->where('pricing_type', 'paid')
            ->get(['id', 'name', 'install_count', 'price'])
            ->map(fn($app) => [
                'id' => $app->id,
                'name' => $app->name,
                'install_count' => $app->install_count,
                'price' => $app->price,
                'gross' => $app->price * $app->install_count,
                'net' => $app->price * $app->install_count * (1 - config('open-platform.commission.platform_fee_percentage', 20) / 100),
            ]);

        return [
            'developer' => $dev,
            'account' => $dev->earningsAccount,
            'earnings_by_app' => $earningsByApp,
            'total_gross' => $earningsByApp->sum('gross'),
            'total_net' => $earningsByApp->sum('net'),
        ];
    }

    /**
     * 开发者提现
     */
    public function developerWithdraw(int $developerId, float $amount, string $channel, array $channelInfo = []): Withdrawal
    {
        $dev = MarketplaceDeveloper::with('earningsAccount')->findOrFail($developerId);
        $account = $dev->earningsAccount;

        if (!$account || $account->available_balance < $amount) {
            throw new \RuntimeException(__('app.open_platform.insufficient_balance'));
        }

        $minPayout = config('open-platform.commission.payout_minimum', 100);
        if ($amount < $minPayout) {
            throw new \RuntimeException(__('app.open_platform.min_withdrawal', ['amount' => $minPayout]));
        }

        return DB::transaction(function () use ($dev, $account, $amount, $channel, $channelInfo) {
            $fee = $amount * 0.01; // 1% 提现手续费
            $netAmount = $amount - $fee;

            $withdrawal = Withdrawal::create([
                'earnings_account_id' => $account->id,
                'user_id' => $dev->user_id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'channel' => $channel,
                'channel_account' => $channelInfo['account'] ?? null,
                'bank_name' => $channelInfo['bank_name'] ?? null,
                'bank_account_name' => $channelInfo['account_name'] ?? null,
                'bank_account_no' => $channelInfo['account_no'] ?? null,
                'alipay_account' => $channel === 'alipay' ? ($channelInfo['account'] ?? null) : null,
                'wechat_account' => $channel === 'wechat' ? ($channelInfo['account'] ?? null) : null,
                'paypal_email' => $channel === 'paypal' ? ($channelInfo['account'] ?? null) : null,
                'status' => 'pending_review',
            ]);

            $account->decrement('available_balance', $amount);
            $dev->increment('total_withdrawn', $amount);

            return $withdrawal->fresh();
        });
    }

    /**
     * 开发者提现记录
     */
    public function getDeveloperWithdrawals(int $developerId, int $perPage = 20)
    {
        $dev = MarketplaceDeveloper::findOrFail($developerId);
        return Withdrawal::where('user_id', $dev->user_id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * 开发者税务信息
     */
    public function updateDeveloperTaxInfo(int $developerId, array $data): MarketplaceDeveloper
    {
        $dev = MarketplaceDeveloper::findOrFail($developerId);
        $dev->update([
            'tax_id' => $data['tax_id'] ?? $dev->tax_id,
            'tax_info' => $data,
        ]);
        return $dev;
    }

    /**
     * 开发者财务总览（管理后台用）
     */
    public function getFinancialDashboard(): array
    {
        $totalDevEarnings = MarketplaceDeveloper::sum('total_earned');
        $totalDevWithdrawn = MarketplaceDeveloper::sum('total_withdrawn');
        $totalApps = MarketplaceApp::where('pricing_type', 'paid')->count();
        $totalPaidInstalls = MarketplaceApp::where('pricing_type', 'paid')->sum('install_count');

        $pendingWithdrawals = Withdrawal::whereIn('status', ['pending_review', 'pending'])->count();
        $pendingAmount = Withdrawal::whereIn('status', ['pending_review', 'pending'])->sum('amount');

        return [
            'total_dev_earnings' => (float) $totalDevEarnings,
            'total_dev_withdrawn' => (float) $totalDevWithdrawn,
            'total_paid_apps' => $totalApps,
            'total_paid_installs' => $totalPaidInstalls,
            'pending_withdrawals' => $pendingWithdrawals,
            'pending_amount' => (float) $pendingAmount,
        ];
    }

    public function getMetadata(): array
    {
        return [
            'categories' => collect(self::CATEGORIES)->map(fn ($value) => [
                'value' => $value,
                'label' => __('app.open_platform.category_' . $value),
            ])->values(),
            'app_statuses' => self::APP_STATUSES,
            'pricing_types' => [
                ['value' => 'free', 'label' => __('app.open_platform.pricing_free')],
                ['value' => 'paid', 'label' => __('app.open_platform.pricing_onetime')],
                ['value' => 'subscription', 'label' => __('app.open_platform.pricing_subscription')],
            ],
        ];
    }
}
