<?php

namespace App\Observers;

use App\Models\Log;
use App\Services\MerkleTreeService;

/**
 * 审计日志观察者
 *
 * 日志创建后自动计算 Merkle 哈希并更新哈希链。
 */
class LogObserver
{
    public function __construct(
        protected MerkleTreeService $merkleTreeService,
    ) {}

    /**
     * 日志创建后自动计算 Merkle 哈希
     */
    public function created(Log $log): void
    {
        $this->merkleTreeService->hashLog($log);
    }
}
