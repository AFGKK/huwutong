<?php

namespace App\Services;

use App\Enums\AiMemorySource;
use App\Enums\AiMemoryType;
use App\Models\AiMemory;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    // ═══════════════════════════════════════════
    //  基础 CRUD
    // ═══════════════════════════════════════════

    /**
     * 存储一条记忆
     */
    public function store(
        int $userId,
        string $key,
        string $content,
        string $type = 'fact',
        string $source = 'manual',
        float $confidence = 0.8,
        int $priority = 0,
        ?string $category = null,
        ?array $tags = null,
        ?int $tenantId = null,
        ?string $expiresAt = null,
    ): AiMemory {
        return AiMemory::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'key' => $key,
            'content' => $content,
            'type' => $type,
            'source' => $source,
            'confidence' => $confidence,
            'priority' => $priority,
            'category' => $category,
            'tags' => $tags,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    /**
     * 批量存储记忆
     */
    public function storeBatch(int $userId, array $memories, ?int $tenantId = null): Collection
    {
        $created = collect();

        DB::transaction(function () use ($userId, $memories, $tenantId, &$created) {
            foreach ($memories as $m) {
                $created->push($this->store(
                    $userId,
                    $m['key'] ?? 'memory_' . uniqid(),
                    $m['content'],
                    $m['type'] ?? 'fact',
                    $m['source'] ?? 'manual',
                    $m['confidence'] ?? 0.8,
                    $m['priority'] ?? 0,
                    $m['category'] ?? null,
                    $m['tags'] ?? null,
                    $tenantId,
                    $m['expires_at'] ?? null,
                ));
            }
        });

        return $created;
    }

    /**
     * 更新一条记忆
     */
    public function update(int $id, array $data): ?AiMemory
    {
        $memory = AiMemory::find($id);
        if (! $memory) return null;

        $memory->update($data);
        return $memory->fresh();
    }

    /**
     * 软删除记忆
     */
    public function forget(int $id, int $userId): bool
    {
        $memory = AiMemory::where('id', $id)->where('user_id', $userId)->first();
        if (! $memory) return false;

        $memory->delete();
        return true;
    }

    /**
     * 批量软删除
     */
    public function forgetBatch(array $ids, int $userId): int
    {
        return AiMemory::whereIn('id', $ids)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * 永久删除过期记忆
     */
    public function forceDeleteExpired(): int
    {
        return AiMemory::where('is_active', false)
            ->orWhere(function ($q) {
                $q->whereNotNull('expires_at')->where('expires_at', '<', now());
            })
            ->forceDelete();
    }

    // ═══════════════════════════════════════════
    //  查询
    // ═══════════════════════════════════════════

    /**
     * 获取用户的所有活跃记忆
     */
    public function list(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AiMemory::byUser($userId)->active();

        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        if (! empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        if (! empty($filters['source'])) {
            $query->bySource($filters['source']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['sort'])) {
            $dir = $filters['sort_dir'] ?? 'desc';
            $query->orderBy($filters['sort'], $dir);
        } else {
            $query->orderBy('priority', 'desc')
                ->orderBy('confidence', 'desc')
                ->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取单条记忆详情
     */
    public function find(int $id, int $userId): ?AiMemory
    {
        return AiMemory::where('id', $id)->where('user_id', $userId)->first();
    }

    /**
     * 按分类统计记忆数量
     */
    public function statsByCategory(int $userId): Collection
    {
        return AiMemory::byUser($userId)
            ->active()
            ->select('category', DB::raw('count(*) as total'), DB::raw('avg(confidence) as avg_confidence'))
            ->groupBy('category')
            ->get();
    }

    /**
     * 按类型统计记忆数量
     */
    public function statsByType(int $userId): Collection
    {
        return AiMemory::byUser($userId)
            ->active()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get();
    }

    /**
     * 获取总览统计
     */
    public function getDashboard(int $userId): array
    {
        $base = AiMemory::byUser($userId);

        return [
            'total' => (clone $base)->active()->count(),
            'by_type' => $this->statsByType($userId),
            'by_category' => $this->statsByCategory($userId),
            'recent' => (clone $base)->active()->orderBy('created_at', 'desc')->take(5)->get(),
            'expiring_soon' => (clone $base)->active()->expiringSoon(7)->count(),
            'high_priority' => (clone $base)->active()->where('priority', '>=', 5)->count(),
            'avg_confidence' => round((clone $base)->active()->avg('confidence') ?? 0, 4),
        ];
    }

    // ═══════════════════════════════════════════
    //  LLM 上下文注入
    // ═══════════════════════════════════════════

    /**
     * 获取用户的记忆上下文文本（用于注入 LLM 对话）
     */
    public function getContextForUser(int $userId, ?string $category = null, int $max = 10): string
    {
        $query = AiMemory::byUser($userId)->active()->important();

        if ($category) {
            $query->byCategory($category);
        }

        $memories = $query->take($max)->get();

        if ($memories->isEmpty()) {
            return '';
        }

        $lines = ['【我对你的了解】'];
        foreach ($memories as $i => $m) {
            $source = $m->sourceLabel();
            $type = $m->typeLabel();
            $lines[] = sprintf("%d. [%s][%s] %s", $i + 1, $type, $source, $m->content);
        }

        return implode("\n", $lines);
    }

    /**
     * 获取结构化的记忆数组（用于系统提示）
     */
    public function getStructuredMemories(int $userId, int $max = 10): array
    {
        return AiMemory::byUser($userId)
            ->active()
            ->important(0.5)
            ->take($max)
            ->get()
            ->map(fn (AiMemory $m) => [
                'id' => $m->id,
                'content' => $m->content,
                'type' => $m->type,
                'category' => $m->category,
                'confidence' => $m->confidence,
                'priority' => $m->priority,
                'source' => $m->source,
            ])
            ->toArray();
    }

    /**
     * 确认（更新置信度）一条记忆，标记为高可信
     */
    public function confirm(int $id, int $userId): ?AiMemory
    {
        $memory = AiMemory::where('id', $id)->where('user_id', $userId)->first();
        if (! $memory) return null;

        $memory->update([
            'confidence' => min(1.0, $memory->confidence + 0.15),
            'priority' => $memory->priority + 1,
        ]);

        return $memory->fresh();
    }

    /**
     * 纠正/更新记忆内容
     */
    public function correct(int $id, int $userId, string $correctedContent): ?AiMemory
    {
        $memory = AiMemory::where('id', $id)->where('user_id', $userId)->first();
        if (! $memory) return null;

        $memory->update([
            'content' => $correctedContent,
            'confidence' => min(1.0, $memory->confidence + 0.1),
            'source' => AiMemorySource::Manual->value,
        ]);

        return $memory->fresh();
    }

    // ═══════════════════════════════════════════
    //  维护
    // ═══════════════════════════════════════════

    /**
     * 清理过期和低置信度记忆
     */
    public function prune(): array
    {
        $expired = AiMemory::where(function ($q) {
            $q->where('is_active', false)
                ->orWhere(function ($qq) {
                    $qq->whereNotNull('expires_at')->where('expires_at', '<', now());
                });
        })->forceDelete();

        $lowConfidence = AiMemory::active()
            ->where('confidence', '<', config('ai-memory.pruning.min_confidence_to_keep', 0.1))
            ->forceDelete();

        return [
            'expired_deleted' => $expired,
            'low_confidence_deleted' => $lowConfidence,
        ];
    }
}
