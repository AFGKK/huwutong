<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomerFeedback;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __construct(
        protected FeedbackService $service,
    ) {}

    // ═══════════ 管理端 ═══════════

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->list(
            $request->only(['status', 'type', 'priority', 'assigned_to', 'search',
                'rating_min', 'rating_max', 'date_from', 'date_to', 'tag_id']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function show(CustomerFeedback $feedback): JsonResponse
    {
        $feedback->load(['customer', 'user', 'assignee', 'tags']);
        return ApiResponse::success($feedback);
    }

    public function update(Request $request, CustomerFeedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:general,bug,feature_request,performance,ui_ux,other',
            'priority' => 'nullable|in:low,normal,high,critical',
            'status' => 'nullable|in:new,under_review,acknowledged,in_progress,resolved,closed,wont_fix',
            'admin_reply' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:feedback_tags,id',
        ]);

        $updated = $this->service->update($feedback->id, $validated);
        return ApiResponse::success($updated, '反馈已更新');
    }

    public function assign(Request $request, CustomerFeedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $updated = $this->service->assign($feedback->id, $validated['user_id']);
        return ApiResponse::success($updated, '已分配处理人');
    }

    public function reply(Request $request, CustomerFeedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $updated = $this->service->reply($feedback->id, $validated['message']);
        return ApiResponse::success($updated, '已回复');
    }

    public function resolve(Request $request, CustomerFeedback $feedback): JsonResponse
    {
        $status = $request->input('status', 'resolved');
        $updated = $this->service->resolve($feedback->id, $status);
        return ApiResponse::success($updated, '反馈状态已更新');
    }

    public function destroy(CustomerFeedback $feedback): JsonResponse
    {
        $feedback->delete();
        return ApiResponse::success(null, '反馈已删除');
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }

    // ═══════════ 标签管理 ═══════════

    public function tags(): JsonResponse
    {
        return ApiResponse::success($this->service->listTags());
    }

    public function storeTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:feedback_tags,name',
            'color' => 'nullable|string|max:7',
        ]);
        return ApiResponse::success($this->service->createTag($validated), '标签已创建', 201);
    }

    // ═══════════ 投票系统 ═══════════

    /**
     * 投票（点赞/点踩）
     */
    public function vote(Request $request, CustomerFeedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'vote' => 'required|integer|in:-1,1',
        ]);

        $result = $this->service->vote($feedback->id, $request->user(), $validated['vote']);

        return ApiResponse::success($result, '投票成功');
    }

    /**
     * 获取投票统计
     */
    public function voteStats(): JsonResponse
    {
        return ApiResponse::success($this->service->getVoteStats());
    }

    // ═══════════ 门户端 ═══════════

    public function myFeedback(Request $request): JsonResponse
    {
        $data = $this->service->myFeedback(
            $request->user(),
            $request->only(['status']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:general,bug,feature_request,performance,ui_ux,other',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:10000',
            'rating' => 'nullable|integer|min:1|max:5',
            'page_url' => 'nullable|string|max:500',
            'page_title' => 'nullable|string|max:255',
            'component_path' => 'nullable|string|max:255',
            'screen_resolution' => 'nullable|string|max:20',
            'screenshots' => 'nullable|array',
            'screenshots.*' => 'string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string',
            'annotations' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:feedback_tags,id',
        ]);

        $feedback = $this->service->create($validated, $request->user());
        return ApiResponse::success($feedback, '感谢您的反馈！', 201);
    }

    public function myShow(CustomerFeedback $feedback): JsonResponse
    {
        if ($feedback->user_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权查看此反馈', 403);
        }
        $feedback->load(['tags']);
        return ApiResponse::success($feedback);
    }
}
