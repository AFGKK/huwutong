<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\KbAutoGrowDraft;
use App\Services\KbAutoGrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KbAutoGrowController extends Controller
{
    protected KbAutoGrowService $service;

    public function __construct(KbAutoGrowService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取统计信息
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }

    /**
     * 获取待审核草稿列表
     */
    public function pending(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $sourceType = $request->input('source_type');

        $query = KbAutoGrowDraft::with('kbArticle:id,title')
            ->orderBy('confidence', 'desc')
            ->orderBy('created_at', 'desc');

        if ($sourceType) {
            $query->where('source_type', $sourceType);
        }

        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'pending');
        }

        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 审核通过
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        $article = $this->service->approve($id, auth()->id());
        if (!$article) {
            return ApiResponse::error('审核失败，草稿可能已处理', 400);
        }

        return ApiResponse::success([
            'article_id' => $article->id,
            'title' => $article->title,
        ], '已通过并发布为知识库文章');
    }

    /**
     * 审核拒绝
     */
    public function reject(int $id): JsonResponse
    {
        $ok = $this->service->reject($id, auth()->id());
        if (!$ok) {
            return ApiResponse::error('拒绝失败，草稿可能已处理', 400);
        }

        return ApiResponse::success(null, '已拒绝');
    }

    /**
     * 手动触发一次扫描
     */
    public function run(Request $request): JsonResponse
    {
        $sources = $request->input('sources', ['rag_chat', 'handoff', 'forum_post', 'im_chat']);
        $limit = (int) $request->input('limit', 20);

        $results = $this->service->run([
            'sources' => $sources,
            'limit_per_source' => $limit,
        ]);

        return ApiResponse::success($results, '扫描完成');
    }
}
