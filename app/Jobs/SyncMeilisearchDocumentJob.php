<?php

namespace App\Jobs;

use App\Services\MeilisearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * D-36: 异步增量同步 Meilisearch 文档
 */
class SyncMeilisearchDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public array $backoff = [5, 15, 60];

    public function __construct(
        public string $action,
        public string $modelClass,
        public int $modelId,
    ) {}

    public function handle(MeilisearchService $meilisearch): void
    {
        if (! $meilisearch->isAvailable()) {
            return;
        }

        if ($this->action === 'delete') {
            $stub = new $this->modelClass();
            $indexKey = $meilisearch->indexKeyForModel($stub);
            $indexUid = $indexKey ? $meilisearch->indexUidForKey($indexKey) : null;
            if ($indexUid) {
                $meilisearch->deleteDocument($indexUid, $this->modelId);
            }

            return;
        }

        $model = $this->modelClass::query()->find($this->modelId);
        if (! $model) {
            return;
        }

        try {
            $meilisearch->upsertModel($model);
        } catch (\Throwable $e) {
            Log::warning('Meilisearch 队列同步失败: ' . $e->getMessage(), [
                'model' => $this->modelClass,
                'id' => $this->modelId,
            ]);

            throw $e;
        }
    }
}
