<?php

namespace App\Services;

use App\Models\ApiChangelog;
use App\Models\ApiVersion;
use App\Models\ApiDocEndpoint;
use App\Models\ApiEndpointSnapshot;
use Illuminate\Support\Facades\DB;

class ChangelogService
{
    const CHANGE_TYPES = ['added', 'modified', 'deprecated', 'removed', 'reactivated', 'security'];

    /**
     * 获取 Changelog 列表
     */
    public function list(array $filters = [], int $perPage = 20)
    {
        $query = ApiChangelog::orderByDesc('release_date')->orderByDesc('created_at');

        if (!empty($filters['version'])) {
            $query->where('version', $filters['version']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('release_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('release_date', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取单个 Changelog
     */
    public function find(int $id): ApiChangelog
    {
        return ApiChangelog::findOrFail($id);
    }

    /**
     * 创建 Changelog
     */
    public function create(array $data): ApiChangelog
    {
        return DB::transaction(function () use ($data) {
            $changelog = ApiChangelog::create([
                'version' => $data['version'],
                'release_date' => $data['release_date'] ?? now(),
                'type' => $data['type'] ?? 'release',
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'affected_endpoints' => $data['affected_endpoints'] ?? [],
                'migration_guide' => $data['migration_guide'] ?? '',
                'source' => $data['source'] ?? 'manual',
                'snapshot_id' => $data['snapshot_id'] ?? null,
            ]);

            return $changelog->fresh();
        });
    }

    /**
     * 更新 Changelog
     */
    public function update(ApiChangelog $changelog, array $data): ApiChangelog
    {
        $changelog->update($data);
        return $changelog->fresh();
    }

    /**
     * 删除 Changelog
     */
    public function delete(ApiChangelog $changelog): bool
    {
        return $changelog->delete();
    }

    /**
     * 自动检测端点变更并生成 Changelog
     */
    public function autoGenerate(int $apiVersionId): array
    {
        $version = ApiVersion::findOrFail($apiVersionId);
        $versionLabel = $version->version;

        $lastSnapshot = $this->getLatestSnapshotVersion($apiVersionId);

        if (!$lastSnapshot) {
            $count = $this->createSnapshot($apiVersionId, $versionLabel);
            return [
                'status' => 'snapshot_created',
                'message' => "已创建首个端点快照，共 {$count} 个端点",
                'changelogs_created' => 0,
                'changes' => [],
            ];
        }

        // 获取上次快照
        $snapshots = ApiEndpointSnapshot::where('api_version_id', $apiVersionId)
            ->where('snapshot_version', $lastSnapshot)
            ->get()
            ->keyBy(fn($s) => $s->method . ' ' . $s->path);

        // 获取当前端点
        $current = ApiDocEndpoint::where('api_version_id', $apiVersionId)
            ->get()
            ->keyBy(fn($e) => $e->method . ' ' . $e->path);

        // 差异对比
        $changes = $this->diffEndpoints($snapshots, $current);

        if (empty($changes['added']) && empty($changes['removed']) && empty($changes['modified']) && empty($changes['deprecated'])) {
            return [
                'status' => 'no_changes',
                'message' => '自上次快照以来无端点变更',
                'changelogs_created' => 0,
                'changes' => $changes,
            ];
        }

        // 生成 Changelog 条目
        $changelogsCreated = 0;

        if (!empty($changes['added']) || !empty($changes['modified']) || !empty($changes['deprecated'])) {
            $description = $this->buildDescription($changes, $versionLabel);

            $changelog = $this->create([
                'version' => $versionLabel,
                'release_date' => now(),
                'type' => 'release',
                'title' => "v{$versionLabel} API 更新",
                'description' => $description,
                'affected_endpoints' => $changes,
                'source' => 'auto_detect',
            ]);

            $changelogsCreated++;
        }

        // 创建新快照
        $this->createSnapshot($apiVersionId, $versionLabel);

        return [
            'status' => 'success',
            'message' => "自动检测完成，生成 {$changelogsCreated} 条 Changelog",
            'changelogs_created' => $changelogsCreated,
            'changes' => $changes,
        ];
    }

    /**
     * 创建端点快照
     */
    public function createSnapshot(int $apiVersionId, string $versionLabel): int
    {
        $endpoints = ApiDocEndpoint::where('api_version_id', $apiVersionId)->get();
        $count = 0;

        foreach ($endpoints as $ep) {
            ApiEndpointSnapshot::create([
                'api_version_id' => $apiVersionId,
                'endpoint_id' => $ep->id,
                'method' => $ep->method,
                'path' => $ep->path,
                'group' => $ep->group,
                'tag' => $ep->tag,
                'summary' => $ep->summary,
                'status' => $ep->status ?? 'active',
                'parameters_snapshot' => $ep->parameters,
                'responses_snapshot' => $ep->responses,
                'snapshot_version' => $versionLabel,
                'snapshot_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 获取自动检测历史
     */
    public function getAutoDetectionHistory(): array
    {
        $snapshots = ApiEndpointSnapshot::select('snapshot_version', 'api_version_id', 'snapshot_at')
            ->distinct()
            ->orderByDesc('snapshot_at')
            ->get()
            ->groupBy('snapshot_version');

        $history = [];
        foreach ($snapshots as $version => $items) {
            $first = $items->first();
            $history[] = [
                'version' => $version,
                'api_version_id' => $first->api_version_id,
                'snapshot_at' => $first->snapshot_at,
                'endpoint_count' => $items->count(),
            ];
        }

        return $history;
    }

    /**
     * 生成大版本迁移指南
     */
    public function generateMigrationGuide(string $fromVersion, string $toVersion): array
    {
        $fromChangelogs = ApiChangelog::where('version', $fromVersion)->get();
        $toChangelogs = ApiChangelog::where('version', $toVersion)->get();
        $betweenChangelogs = ApiChangelog::whereBetween('version', [$fromVersion, $toVersion])
            ->orderBy('release_date')
            ->get();

        // 收集所有受影响端点
        $allAffected = [];
        $breakingChanges = [];

        foreach ($betweenChangelogs as $log) {
            if (!empty($log->affected_endpoints)) {
                $endpoints = is_string($log->affected_endpoints)
                    ? json_decode($log->affected_endpoints, true)
                    : $log->affected_endpoints;

                if (isset($endpoints['removed'])) {
                    foreach ($endpoints['removed'] as $ep) {
                        $breakingChanges[] = [
                            'type' => 'removed',
                            'endpoint' => $ep['method'] . ' ' . $ep['path'],
                            'summary' => $ep['summary'] ?? '',
                            'version' => $log->version,
                        ];
                    }
                }
                if (isset($endpoints['deprecated'])) {
                    foreach ($endpoints['deprecated'] as $ep) {
                        $breakingChanges[] = [
                            'type' => 'deprecated',
                            'endpoint' => $ep['method'] . ' ' . $ep['path'],
                            'summary' => $ep['summary'] ?? '',
                            'version' => $log->version,
                        ];
                    }
                }

                $allAffected[] = $endpoints;
            }
        }

        return [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'changelog_count' => $betweenChangelogs->count(),
            'breaking_changes' => $breakingChanges,
            'affected_endpoints' => $allAffected,
            'migration_steps' => $this->buildMigrationSteps($breakingChanges),
            'recommended_upgrade_path' => $this->buildUpgradePath($fromVersion, $toVersion),
        ];
    }

    /**
     * 统计概览
     */
    public function stats(): array
    {
        $total = ApiChangelog::count();
        $latestVersion = ApiChangelog::orderByDesc('release_date')->first();

        return [
            'total_changelogs' => $total,
            'total_versions' => ApiChangelog::distinct('version')->count('version'),
            'latest_version' => $latestVersion?->version,
            'latest_release_date' => $latestVersion?->release_date,
            'by_type' => ApiChangelog::selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'total_snapshots' => ApiEndpointSnapshot::distinct('snapshot_version')->count('snapshot_version'),
        ];
    }

    // ─── 私有方法 ───

    private function getLatestSnapshotVersion(int $apiVersionId): ?string
    {
        $latest = ApiEndpointSnapshot::where('api_version_id', $apiVersionId)
            ->orderByDesc('snapshot_at')
            ->first();

        return $latest ? $latest->snapshot_version : null;
    }

    private function diffEndpoints($snapshots, $current): array
    {
        $added = [];
        $removed = [];
        $modified = [];
        $deprecated = [];
        $reactivated = [];

        $allKeys = $snapshots->keys()->merge($current->keys())->unique()->sort();

        foreach ($allKeys as $key) {
            $old = $snapshots->get($key);
            $new = $current->get($key);

            if ($old && !$new) {
                $removed[] = [
                    'key' => $key,
                    'method' => $old->method,
                    'path' => $old->path,
                    'summary' => $old->summary,
                    'old_status' => $old->status,
                ];
            } elseif (!$old && $new) {
                $added[] = [
                    'key' => $key,
                    'method' => $new->method,
                    'path' => $new->path,
                    'summary' => $new->summary,
                    'status' => $new->status ?? 'active',
                ];
            } elseif ($old && $new) {
                $changes = $this->detectEndpointChanges($old, $new);

                if ($old->status !== $new->status) {
                    if ($new->status === 'deprecated') {
                        $deprecated[] = [
                            'key' => $key,
                            'method' => $new->method,
                            'path' => $new->path,
                            'summary' => $new->summary,
                            'old_status' => $old->status,
                            'new_status' => $new->status,
                            'changes' => $changes,
                        ];
                    } elseif ($old->status === 'deprecated' && in_array($new->status, ['active', 'beta'])) {
                        $reactivated[] = [
                            'key' => $key,
                            'method' => $new->method,
                            'path' => $new->path,
                            'summary' => $new->summary,
                            'old_status' => $old->status,
                            'new_status' => $new->status,
                            'changes' => $changes,
                        ];
                    }
                }

                if (!empty($changes)) {
                    $modified[] = [
                        'key' => $key,
                        'method' => $new->method,
                        'path' => $new->path,
                        'summary' => $new->summary,
                        'changes' => $changes,
                    ];
                }
            }
        }

        return compact('added', 'removed', 'modified', 'deprecated', 'reactivated');
    }

    private function detectEndpointChanges($old, $new): array
    {
        $changes = [];

        if ($old->summary !== $new->summary) {
            $changes[] = ['field' => 'summary', 'from' => $old->summary, 'to' => $new->summary];
        }
        if ($old->group !== $new->group) {
            $changes[] = ['field' => 'group', 'from' => $old->group, 'to' => $new->group];
        }

        $oldParams = is_string($old->parameters_snapshot) ? json_decode($old->parameters_snapshot, true) : ($old->parameters_snapshot ?? []);
        $newParams = is_string($new->parameters) ? json_decode($new->parameters, true) : ($new->parameters ?? []);

        if (!empty($oldParams) || !empty($newParams)) {
            $oldParamKeys = collect($oldParams)->pluck('name')->toArray();
            $newParamKeys = collect($newParams)->pluck('name')->toArray();

            $added = array_diff($newParamKeys, $oldParamKeys);
            $removed = array_diff($oldParamKeys, $newParamKeys);

            if (!empty($added)) {
                $changes[] = ['field' => 'parameters', 'type' => 'added', 'params' => array_values($added)];
            }
            if (!empty($removed)) {
                $changes[] = ['field' => 'parameters', 'type' => 'removed', 'params' => array_values($removed)];
            }
        }

        return $changes;
    }

    private function buildDescription(array $changes, string $versionLabel): string
    {
        $parts = [];

        if (!empty($changes['added'])) {
            $parts[] = "**新增端点**（" . count($changes['added']) . "个）";
            foreach (array_slice($changes['added'], 0, 10) as $ep) {
                $parts[] = "- `{$ep['method']} {$ep['path']}` — {$ep['summary']}";
            }
            if (count($changes['added']) > 10) {
                $parts[] = "- ...及其他 " . (count($changes['added']) - 10) . " 个新增端点";
            }
        }

        if (!empty($changes['modified'])) {
            $parts[] = "**修改端点**（" . count($changes['modified']) . "个）";
            foreach (array_slice($changes['modified'], 0, 10) as $ep) {
                $parts[] = "- `{$ep['method']} {$ep['path']}` — {$ep['summary']}";
            }
        }

        if (!empty($changes['deprecated'])) {
            $parts[] = "**弃用端点**（" . count($changes['deprecated']) . "个）";
            foreach ($changes['deprecated'] as $ep) {
                $parts[] = "- `{$ep['method']} {$ep['path']}` — 请迁移至替代方案";
            }
        }

        if (!empty($changes['removed'])) {
            $parts[] = "**移除端点**（" . count($changes['removed']) . "个）";
            foreach ($changes['removed'] as $ep) {
                $parts[] = "- `{$ep['method']} {$ep['path']}` — 此前已标记弃用";
            }
        }

        return implode("\n\n", $parts);
    }

    private function buildMigrationSteps(array $breakingChanges): array
    {
        $steps = [];

        foreach ($breakingChanges as $change) {
            if ($change['type'] === 'removed') {
                $steps[] = "🔴 端点 `{$change['endpoint']}` 已移除。请更新您的集成代码，使用替代 API。";
            } elseif ($change['type'] === 'deprecated') {
                $steps[] = "🟡 端点 `{$change['endpoint']}` 已标记弃用。请计划迁移至替代方案。";
            }
        }

        if (empty($steps)) {
            $steps[] = "✅ 本次升级无破坏性变更，可安全升级。";
        }

        return $steps;
    }

    private function buildUpgradePath(string $fromVersion, string $toVersion): string
    {
        $intermediate = ApiChangelog::whereBetween('version', [$fromVersion, $toVersion])
            ->distinct('version')
            ->pluck('version')
            ->toArray();

        if (count($intermediate) <= 2) {
            return "建议直接从 v{$fromVersion} 升级至 v{$toVersion}";
        }

        return "建议按以下版本顺序逐步升级：" . implode(' → ', $intermediate);
    }
}
