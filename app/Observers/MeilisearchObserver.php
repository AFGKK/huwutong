<?php

namespace App\Observers;

use App\Services\MeilisearchIndexer;
use Illuminate\Database\Eloquent\Model;

/**
 * D-36: 模型 CUD 自动增量同步 Meilisearch 索引
 */
class MeilisearchObserver
{
    public function __construct(
        protected MeilisearchIndexer $indexer,
    ) {}

    public function created(Model $model): void
    {
        $this->indexer->sync($model);
    }

    public function updated(Model $model): void
    {
        $this->indexer->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->indexer->remove($model);
    }

    public function restored(Model $model): void
    {
        $this->indexer->sync($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->indexer->remove($model);
    }
}
