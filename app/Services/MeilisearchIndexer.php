<?php

namespace App\Services;

use App\Jobs\SyncMeilisearchDocumentJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * D-36: Meilisearch Model Observer 增量同步调度
 */
class MeilisearchIndexer
{
    public function __construct(
        protected MeilisearchService $meilisearch,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('meilisearch.observer.enabled', true);
    }

    public function usesQueue(): bool
    {
        return (bool) config('meilisearch.sync.queue', false);
    }

    public function sync(Model $model): void
    {
        if (! $this->enabled() || ! $this->meilisearch->indexKeyForModel($model)) {
            return;
        }

        if ($this->usesQueue()) {
            SyncMeilisearchDocumentJob::dispatch('upsert', $model::class, (int) $model->getKey())
                ->onQueue(config('meilisearch.sync.queue_name', 'default'));

            return;
        }

        try {
            $this->meilisearch->upsertModel($model);
        } catch (\Throwable $e) {
            Log::warning('Meilisearch 增量同步失败: ' . $e->getMessage(), [
                'model' => $model::class,
                'id' => $model->getKey(),
            ]);
        }
    }

    public function remove(Model $model): void
    {
        if (! $this->enabled() || ! $this->meilisearch->indexKeyForModel($model)) {
            return;
        }

        if ($this->usesQueue()) {
            SyncMeilisearchDocumentJob::dispatch('delete', $model::class, (int) $model->getKey())
                ->onQueue(config('meilisearch.sync.queue_name', 'default'));

            return;
        }

        try {
            $this->meilisearch->removeModel($model);
        } catch (\Throwable $e) {
            Log::warning('Meilisearch 增量删除失败: ' . $e->getMessage(), [
                'model' => $model::class,
                'id' => $model->getKey(),
            ]);
        }
    }
}
