<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\RagDocument;
use App\Services\RagEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RagController extends Controller
{
    public function __construct(
        protected RagEngineService $ragService,
    ) {}

    /**
     * 检索相关文档
     */
    public function retrieve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:500',
            'min_confidence' => 'sometimes|numeric|min:0|max:1',
            'max_results' => 'sometimes|integer|min:1|max:20',
            'locale' => 'sometimes|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->ragService->retrieve($request->input('q'), [
            'min_confidence' => $request->input('min_confidence', 0.35),
            'max_results' => $request->input('max_results', 5),
            'locale' => $request->input('locale', 'zh-CN'),
        ]);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 提问（检索 + 回答）
     */
    public function ask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:2000',
            'session_id' => 'sometimes|string|max:100',
            'locale' => 'sometimes|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->ragService->answer(
            $request->input('q'),
            $request->input('session_id'),
            [
                'user_id' => $request->user()?->id,
                'locale' => $request->input('locale', 'zh-CN'),
            ]
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 获取对话历史
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $messages = $this->ragService->getConversationHistory($request->input('session_id'));

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * 反馈
     */
    public function feedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|integer|exists:rag_messages,id',
            'was_helpful' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->ragService->recordFeedback(
            $request->input('message_id'),
            $request->input('was_helpful')
        );

        return response()->json(['success' => true, 'message' => __('app.api.rag.thanks_feedback')]);
    }

    /**
     * 索引文章（管理）
     */
    public function indexArticle(KbArticle $article): JsonResponse
    {
        $this->authorize('update', KbArticle::class);

        $this->ragService->indexArticle($article);

        return response()->json(['success' => true, 'message' => __('app.api.rag.article_indexed')]);
    }

    /**
     * 重建索引（管理）
     */
    public function rebuildIndex(): JsonResponse
    {
        $this->authorize('update', KbArticle::class);

        $result = $this->ragService->rebuildIndex();

        return response()->json([
            'success' => true,
            'message' => __('app.api.rag.index_rebuilt', ['indexed' => $result['indexed']]),
            'data' => $result,
        ]);
    }

    /**
     * RAG 统计
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ragService->getStats(),
        ]);
    }
}
