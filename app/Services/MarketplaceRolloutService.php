<?php

namespace App\Services;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppInstallation;
use App\Models\MarketplaceAppRollout;
use App\Models\MarketplaceAppRolloutEvent;
use App\Models\MarketplaceAppRolloutTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketplaceRolloutService
{
    /**
     * List rollouts with optional filters.
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $q = MarketplaceAppRollout::with(['app:id,slug,name,icon_url', 'version:id,version', 'creator:id,name']);

        if (!empty($filters['app_id'])) {
            $q->where('app_id', $filters['app_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $q->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhereHas('app', fn($q) => $q->where('name', 'like', "%{$filters['search']}%"));
            });
        }

        return $q->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a single rollout with relations.
     */
    public function show(int $id): MarketplaceAppRollout
    {
        return MarketplaceAppRollout::with([
            'app:id,slug,name,icon_url,current_version',
            'version:id,app_id,version,changelog,released_at',
            'creator:id,name,avatar',
            'tenants.tenant:id,name,domain',
            'events' => fn($q) => $q->latest()->limit(100),
            'rollbacker:id,name',
        ])->findOrFail($id);
    }

    /**
     * Create a new rollout.
     */
    public function create(array $data, User $user): MarketplaceAppRollout
    {
        return DB::transaction(function () use ($data, $user) {
            $rollout = MarketplaceAppRollout::create([
                'app_id' => $data['app_id'],
                'version_id' => $data['version_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rollout_type' => $data['rollout_type'] ?? 'percentage',
                'percentage' => $data['percentage'] ?? 10,
                'target_filters' => $data['target_filters'] ?? null,
                'status' => 'draft',
                'auto_rollback' => $data['auto_rollback'] ?? false,
                'error_threshold' => $data['error_threshold'] ?? 5.00,
                'created_by' => $user->id,
            ]);

            // Save tenant overrides if provided
            if (!empty($data['tenant_ids'])) {
                foreach ($data['tenant_ids'] as $tid) {
                    MarketplaceAppRolloutTenant::create([
                        'rollout_id' => $rollout->id,
                        'tenant_id' => $tid,
                        'included' => true,
                    ]);
                }
            }
            if (!empty($data['excluded_tenant_ids'])) {
                foreach ($data['excluded_tenant_ids'] as $tid) {
                    MarketplaceAppRolloutTenant::create([
                        'rollout_id' => $rollout->id,
                        'tenant_id' => $tid,
                        'included' => false,
                    ]);
                }
            }

            $rollout->load(['app:id,slug,name,icon_url', 'version:id,version', 'creator:id,name']);

            return $rollout;
        });
    }

    /**
     * Update a draft rollout.
     */
    public function update(int $id, array $data): MarketplaceAppRollout
    {
        $rollout = MarketplaceAppRollout::findOrFail($id);
        if (!$rollout->isDraft()) {
            throw new \RuntimeException('只能编辑草稿状态的灰度发布');
        }

        $rollout->update($data);

        // Update tenant overrides
        if (isset($data['tenant_ids'])) {
            $rollout->tenants()->where('included', true)->delete();
            foreach ($data['tenant_ids'] as $tid) {
                MarketplaceAppRolloutTenant::create([
                    'rollout_id' => $rollout->id,
                    'tenant_id' => $tid,
                    'included' => true,
                ]);
            }
        }
        if (isset($data['excluded_tenant_ids'])) {
            $rollout->tenants()->where('included', false)->delete();
            foreach ($data['excluded_tenant_ids'] as $tid) {
                MarketplaceAppRolloutTenant::create([
                    'rollout_id' => $rollout->id,
                    'tenant_id' => $tid,
                    'included' => false,
                ]);
            }
        }

        return $rollout->fresh(['app:id,slug,name', 'version:id,version', 'creator:id,name']);
    }

    /**
     * Start a rollout - assign to target tenants and begin distribution.
     */
    public function start(int $id): MarketplaceAppRollout
    {
        return DB::transaction(function () use ($id) {
            $rollout = MarketplaceAppRollout::with('app')->findOrFail($id);

            if (!$rollout->isDraft() && !$rollout->isPaused()) {
                throw new \RuntimeException('只有草稿或暂停状态的发布才能启动');
            }

            // If starting from paused, just resume
            if ($rollout->isPaused()) {
                $rollout->update(['status' => 'active', 'paused_at' => null]);
                $rollout->events()->create(['event_type' => 'resumed', 'message' => '灰度发布已恢复']);
                return $rollout->fresh(['app:id,slug,name,icon_url', 'version:id,version', 'creator:id,name']);
            }

            // Determine target tenants and assign them
            $targetTenants = $this->resolveTargetTenants($rollout);

            $assignedCount = 0;
            foreach ($targetTenants as $tenant) {
                // Check if tenant already has installation
                $existing = MarketplaceAppInstallation::where('app_id', $rollout->app_id)
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->first();

                if ($existing) {
                    // Record previous version for rollback
                    $existing->update([
                        'previous_version' => $existing->installed_version,
                        'rollout_id' => $rollout->id,
                        'auto_updated' => true,
                    ]);
                }

                // Create rollout assignment event
                $rollout->events()->create([
                    'event_type' => 'assigned',
                    'tenant_id' => $tenant->id,
                    'message' => "租户 {$tenant->name} 已分配到灰度发布",
                    'details' => ['tenant_domain' => $tenant->domain ?? ''],
                ]);

                $assignedCount++;
            }

            $rollout->update([
                'status' => 'active',
                'assigned_count' => $assignedCount,
                'started_at' => now(),
            ]);

            $rollout->events()->create(['event_type' => 'started', 'message' => "灰度发布已启动，分配到 {$assignedCount} 个租户"]);

            return $rollout->fresh(['app:id,slug,name,icon_url', 'version:id,version', 'creator:id,name']);
        });
    }

    /**
     * Pause an active rollout.
     */
    public function pause(int $id): MarketplaceAppRollout
    {
        return DB::transaction(function () use ($id) {
            $rollout = MarketplaceAppRollout::findOrFail($id);
            if (!$rollout->isActive()) {
                throw new \RuntimeException('只有进行中的发布才能暂停');
            }
            $rollout->update(['status' => 'paused', 'paused_at' => now()]);
            $rollout->events()->create(['event_type' => 'paused', 'message' => '灰度发布已暂停']);
            return $rollout->fresh();
        });
    }

    /**
     * Complete a rollout - promote to all tenants.
     */
    public function complete(int $id): MarketplaceAppRollout
    {
        return DB::transaction(function () use ($id) {
            $rollout = MarketplaceAppRollout::with('version')->findOrFail($id);

            if (!$rollout->isActive() && !$rollout->isPaused()) {
                throw new \RuntimeException('只有进行中或暂停的发布才能完成');
            }

            // Update the app's current_version to the rollout version
            $rollout->app()->update(['current_version' => $rollout->version->version]);

            $rollout->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $rollout->events()->create([
                'event_type' => 'completed',
                'message' => "灰度发布已完成，版本 {$rollout->version->version} 已全量上线",
            ]);

            return $rollout->fresh(['app:id,slug,name,icon_url', 'version:id,version', 'creator:id,name']);
        });
    }

    /**
     * Rollback a rollout - revert to previous version.
     */
    public function rollback(int $id, User $user): MarketplaceAppRollout
    {
        return DB::transaction(function () use ($id, $user) {
            $rollout = MarketplaceAppRollout::findOrFail($id);
            if (!$rollout->isActive() && !$rollout->isPaused()) {
                throw new \RuntimeException('只有进行中或暂停的发布才能回滚');
            }

            // Revert installations that were auto-updated
            $updated = MarketplaceAppInstallation::where('rollout_id', $rollout->id)
                ->where('auto_updated', true)
                ->whereNotNull('previous_version')
                ->update([
                    'installed_version' => DB::raw('previous_version'),
                    'previous_version' => null,
                    'auto_updated' => false,
                    'rollout_id' => null,
                ]);

            $rollout->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
                'rolled_back_by' => $user->id,
            ]);

            $rollout->events()->create([
                'event_type' => 'rollback',
                'message' => "灰度发布已回滚，影响 {$updated} 个安装记录",
            ]);

            return $rollout->fresh(['app:id,slug,name,icon_url', 'version:id,version', 'creator:id,name', 'rollbacker:id,name']);
        });
    }

    /**
     * Get rollout statistics.
     */
    public function stats(int $id): array
    {
        $rollout = MarketplaceAppRollout::withCount(['events'])->findOrFail($id);

        $eventCounts = $rollout->events()
            ->selectRaw("event_type, COUNT(*) as cnt")
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type');

        $errorTrend = $rollout->events()
            ->where('event_type', 'error')
            ->selectRaw("DATE(created_at) as date, COUNT(*) as cnt")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $installedTrend = $rollout->events()
            ->where('event_type', 'installed')
            ->selectRaw("DATE(created_at) as date, COUNT(*) as cnt")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'rollout' => $rollout->only(['id', 'status', 'assigned_count', 'installed_count', 'error_count', 'percentage']),
            'error_rate' => $rollout->errorRate(),
            'progress' => $rollout->progressPercent(),
            'event_counts' => $eventCounts,
            'error_trend' => $errorTrend,
            'installed_trend' => $installedTrend,
            'should_auto_rollback' => $rollout->shouldAutoRollback(),
        ];
    }

    /**
     * Record an error event and check for auto-rollback.
     */
    public function recordError(int $rolloutId, string $message, ?int $installationId = null, ?int $tenantId = null): ?MarketplaceAppRollout
    {
        $rollout = MarketplaceAppRollout::findOrFail($rolloutId);

        $rollout->increment('error_count');

        $rollout->events()->create([
            'event_type' => 'error',
            'installation_id' => $installationId,
            'tenant_id' => $tenantId,
            'message' => $message,
        ]);

        // Check auto-rollback
        if ($rollout->shouldAutoRollback()) {
            $rollout->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
            ]);
            $rollout->events()->create([
                'event_type' => 'rollback',
                'message' => "自动回滚：错误率 {$rollout->errorRate()}% 超过阈值 {$rollout->error_threshold}%",
            ]);
        }

        return $rollout->fresh();
    }

    /**
     * Record installation event for a rollout.
     */
    public function recordInstallation(int $rolloutId, int $installationId, int $tenantId): void
    {
        $rollout = MarketplaceAppRollout::findOrFail($rolloutId);
        $rollout->increment('installed_count');

        $rollout->events()->create([
            'event_type' => 'installed',
            'installation_id' => $installationId,
            'tenant_id' => $tenantId,
            'message' => '新版本已安装',
        ]);
    }

    /**
     * Get all available apps with their versions for the rollout creation dropdown.
     */
    public function getAvailableApps(): Collection
    {
        return MarketplaceApp::where('status', 'published')
            ->with(['versions' => function ($q) {
                $q->where('status', 'published')->orderBy('created_at', 'desc');
            }])
            ->get(['id', 'slug', 'name', 'icon_url', 'current_version']);
    }

    /**
     * Get tenants available for targeting.
     */
    public function getAvailableTenants(string $search = '', int $limit = 20): Collection
    {
        return Tenant::where('name', 'like', "%{$search}%")
            ->orWhere('domain', 'like', "%{$search}%")
            ->limit($limit)
            ->get(['id', 'name', 'domain']);
    }

    /**
     * Resolve target tenants based on rollout configuration.
     */
    private function resolveTargetTenants(MarketplaceAppRollout $rollout): Collection
    {
        // Start with all tenants that have the app installed
        $installedTenantIds = MarketplaceAppInstallation::where('app_id', $rollout->app_id)
            ->where('status', 'active')
            ->pluck('tenant_id')
            ->unique();

        // Also include all active tenants
        $allTenants = Tenant::whereIn('id', $installedTenantIds)->get();

        // Exclude explicitly excluded tenants
        $excludedIds = $rollout->tenants()->where('included', false)->pluck('tenant_id');

        if ($excludedIds->isNotEmpty()) {
            $allTenants = $allTenants->reject(fn($t) => $excludedIds->contains($t->id));
        }

        // Get explicitly included tenants
        $includedIds = $rollout->tenants()->where('included', true)->pluck('tenant_id');

        if ($includedIds->isNotEmpty()) {
            // Only target included tenants
            $allTenants = $allTenants->whereIn('id', $includedIds);
        } elseif ($rollout->rollout_type === 'percentage') {
            // Apply percentage filtering
            $total = $allTenants->count();
            $targetCount = max(1, (int) round($total * $rollout->percentage / 100));

            // Deterministic selection based on tenant ID hash for consistency
            $sorted = $allTenants->sortBy(fn($t) => crc32($rollout->id . '-' . $t->id));
            $allTenants = $sorted->take($targetCount);
        }

        return $allTenants;
    }
}
