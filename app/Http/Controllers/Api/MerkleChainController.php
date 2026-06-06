<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MerkleTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merkle 审计链验证 API
 *
 * 提供审计日志 Merkle 哈希链的验证和监控端点。
 */
class MerkleChainController extends Controller
{
    public function __construct(
        protected MerkleTreeService $merkleTreeService,
    ) {}

    /**
     * 获取 Merkle 链统计摘要
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success(
            $this->merkleTreeService->getStats()
        );
    }

    /**
     * 验证审计日志完整性
     *
     * @param Request $request
     * @param int|null $logId 可选：从指定日志开始验证
     */
    public function verify(Request $request, ?int $logId = null): JsonResponse
    {
        if ($request->has('log_id')) {
            $logId = (int) $request->input('log_id');
        }

        $result = $this->merkleTreeService->verify($logId);

        return ApiResponse::success($result);
    }

    /**
     * 手动触发锚定
     */
    public function anchor(Request $request): JsonResponse
    {
        $force = $request->boolean('force', false);
        $backfill = $request->boolean('backfill', false);

        if ($backfill) {
            $count = $this->merkleTreeService->backfillUnhashedLogs();
        }

        $stats = $this->merkleTreeService->getStats();
        if ($stats['unhashed_logs'] > 0 && !$force) {
            return ApiResponse::error(
                'UNHASHED_LOGS',
                "仍有 {$stats['unhashed_logs']} 条日志未哈希，请先回填或使用 force",
                422,
            );
        }

        $anchor = $this->merkleTreeService->anchor();

        return ApiResponse::success($anchor, 'Merkle 根哈希已锚定');
    }

    /**
     * 获取锚定历史
     */
    public function anchors(): JsonResponse
    {
        return ApiResponse::success(
            $this->merkleTreeService->getAnchorHistory()
        );
    }

    /**
     * 回填未哈希的日志
     */
    public function backfill(): JsonResponse
    {
        $count = $this->merkleTreeService->backfillUnhashedLogs();

        return ApiResponse::success([
            'backfilled' => $count,
            'message' => "已回填 {$count} 条日志的 Merkle 哈希",
        ]);
    }
}
