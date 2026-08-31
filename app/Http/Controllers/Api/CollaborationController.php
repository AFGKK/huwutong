<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CannedReply;
use App\Models\Note;
use App\Services\TeamCollaborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CollaborationController extends Controller
{
    public function __construct(
        protected TeamCollaborationService $collab,
    ) {}

    // ══════════════════════════════════════════
    //  笔记
    // ══════════════════════════════════════════

    /**
     * 获取实体笔记列表
     * GET /api/admin/{entityType}/{entityId}/notes
     */
    public function notes(string $entityType, int $entityId, Request $request): JsonResponse
    {
        $subject = $this->resolveEntity($entityType, $entityId);
        if (!$subject) {
            return ApiResponse::notFound(__('app.api.collab.entity_missing'));
        }

        $notes = $this->collab->getNotes($subject, $request->only(['is_internal', 'user_id']));
        return ApiResponse::success($notes);
    }

    /**
     * 创建笔记
     * POST /api/admin/{entityType}/{entityId}/notes
     */
    public function createNote(string $entityType, int $entityId, Request $request): JsonResponse
    {
        $subject = $this->resolveEntity($entityType, $entityId);
        if (!$subject) {
            return ApiResponse::notFound(__('app.api.collab.entity_missing'));
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:10000',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer|exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string|max:255',
            'attachments.*.url' => 'required_with:attachments|string|max:1000',
            'is_pinned' => 'nullable|boolean',
            'is_internal' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $note = $this->collab->createNote($subject, $validator->validated());
        return ApiResponse::created($note, __('app.api.collab.note_created'));
    }

    /**
     * 更新笔记
     * PUT /api/admin/notes/{id}
     */
    public function updateNote(int $id, Request $request): JsonResponse
    {
        $note = Note::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'content' => 'sometimes|string|max:10000',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer|exists:users,id',
            'attachments' => 'nullable|array',
            'is_pinned' => 'nullable|boolean',
            'is_internal' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $note = $this->collab->updateNote($note, $validator->validated());
        return ApiResponse::success($note, __('app.api.collab.note_updated'));
    }

    /**
     * 删除笔记
     * DELETE /api/admin/notes/{id}
     */
    public function deleteNote(int $id): JsonResponse
    {
        $note = Note::findOrFail($id);
        $this->collab->deleteNote($note);
        return ApiResponse::success(null, __('app.api.collab.note_deleted'));
    }

    /**
     * 切换笔记置顶
     * POST /api/admin/notes/{id}/toggle-pin
     */
    public function togglePin(int $id): JsonResponse
    {
        $note = Note::findOrFail($id);
        $isPinned = $this->collab->togglePin($note);
        return ApiResponse::success(['is_pinned' => $isPinned], $isPinned ? __('app.api.collab.pinned') : __('app.api.collab.unpinned'));
    }

    /**
     * 笔记数量统计（批量）
     * POST /api/admin/notes/counts
     */
    public function noteCounts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|string',
            'entity_ids' => 'required|array',
            'entity_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $counts = $this->collab->getNoteCounts($request->input('entity_type'), $request->input('entity_ids'));
        return ApiResponse::success($counts);
    }

    // ══════════════════════════════════════════
    //  变更日志
    // ══════════════════════════════════════════

    /**
     * 获取实体变更日志
     * GET /api/admin/{entityType}/{entityId}/change-logs
     */
    public function changeLogs(string $entityType, int $entityId): JsonResponse
    {
        $subject = $this->resolveEntity($entityType, $entityId);
        if (!$subject) {
            return ApiResponse::notFound(__('app.api.collab.entity_missing'));
        }

        $logs = $this->collab->getChangeLogs($subject);
        return ApiResponse::success($logs);
    }

    // ══════════════════════════════════════════
    //  活动流
    // ══════════════════════════════════════════

    /**
     * 全局活动流
     * GET /api/admin/activity-feed
     */
    public function activityFeed(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $feed = $this->collab->getActivityFeed($tenantId, $request->only(['types', 'user_id', 'date_from', 'date_to']));

        return ApiResponse::paginated($feed);
    }

    /**
     * 个人活动流
     * GET /api/admin/activity-feed/mine
     */
    public function myActivityFeed(Request $request): JsonResponse
    {
        $activities = $this->collab->getUserActivityFeed($request->user()->id, 20);
        return ApiResponse::success($activities);
    }

    /**
     * 批量获取最后活动时间
     * POST /api/admin/activities/last-timestamps
     */
    public function lastActivityTimestamps(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|string',
            'entity_ids' => 'required|array',
            'entity_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $timestamps = $this->collab->getLastActivityTimestamps(
            $request->input('entity_type'),
            $request->input('entity_ids')
        );

        return ApiResponse::success($timestamps);
    }

    // ══════════════════════════════════════════
    //  快捷回复
    // ══════════════════════════════════════════

    /**
     * 快捷回复列表
     * GET /api/admin/canned-replies
     */
    public function cannedReplies(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $replies = $this->collab->getCannedReplies($tenantId, $request->input('category'));
        return ApiResponse::success($replies);
    }

    /**
     * 创建快捷回复
     * POST /api/admin/canned-replies
     */
    public function createCannedReply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:5000',
            'category' => 'nullable|string|in:general,license,ticket,customer',
            'is_shared' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $reply = $this->collab->createCannedReply($validator->validated());
        return ApiResponse::created($reply, __('app.api.collab.quick_reply_created'));
    }

    /**
     * 更新快捷回复
     * PUT /api/admin/canned-replies/{id}
     */
    public function updateCannedReply(int $id, Request $request): JsonResponse
    {
        $reply = CannedReply::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string|max:5000',
            'category' => 'nullable|string|in:general,license,ticket,customer',
            'is_shared' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $reply = $this->collab->updateCannedReply($reply, $validator->validated());
        return ApiResponse::success($reply, __('app.api.collab.quick_reply_updated'));
    }

    /**
     * 删除快捷回复
     * DELETE /api/admin/canned-replies/{id}
     */
    public function deleteCannedReply(int $id): JsonResponse
    {
        $reply = CannedReply::findOrFail($id);
        $this->collab->deleteCannedReply($reply);
        return ApiResponse::success(null, __('app.api.collab.quick_reply_deleted'));
    }

    // ══════════════════════════════════════════
    //  关注
    // ══════════════════════════════════════════

    /**
     * 关注列表
     * GET /api/admin/watchlist
     */
    public function watchlist(Request $request): JsonResponse
    {
        $items = $this->collab->getWatchlist($request->user()->id, $request->input('type'));
        return ApiResponse::success($items);
    }

    /**
     * 切换关注
     * POST /api/admin/{entityType}/{entityId}/toggle-watch
     */
    public function toggleWatch(string $entityType, int $entityId): JsonResponse
    {
        $subject = $this->resolveEntity($entityType, $entityId);
        if (!$subject) {
            return ApiResponse::notFound(__('app.api.collab.entity_missing'));
        }

        $result = $this->collab->toggleWatch($subject);
        return ApiResponse::success($result, $result['message']);
    }

    /**
     * 检查关注状态
     * GET /api/admin/{entityType}/{entityId}/is-watching
     */
    public function isWatching(string $entityType, int $entityId): JsonResponse
    {
        $subject = $this->resolveEntity($entityType, $entityId);
        if (!$subject) {
            return ApiResponse::notFound(__('app.api.collab.entity_missing'));
        }

        $isWatching = $this->collab->isWatching($subject);
        return ApiResponse::success(['is_watching' => $isWatching]);
    }

    // ══════════════════════════════════════════
    //  协作偏好
    // ══════════════════════════════════════════

    /**
     * 获取偏好
     * GET /api/admin/collaboration-preferences
     */
    public function preferences(Request $request): JsonResponse
    {
        $prefs = $this->collab->getPreferences($request->user()->id);
        return ApiResponse::success($prefs);
    }

    /**
     * 更新偏好
     * PUT /api/admin/collaboration-preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notify_on_mention' => 'nullable|boolean',
            'notify_on_note_reply' => 'nullable|boolean',
            'notify_on_status_change' => 'nullable|boolean',
            'daily_digest' => 'nullable|boolean',
            'digest_time' => 'nullable|string|max:5',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.collab.param_validation'), $validator->errors()->toArray());
        }

        $prefs = $this->collab->updatePreferences($request->user()->id, $validator->validated());
        return ApiResponse::success($prefs, __('app.api.collab.prefs_updated'));
    }

    // ══════════════════════════════════════════
    //  辅助方法
    // ══════════════════════════════════════════

    /**
     * 根据类型和 ID 解析实体模型
     */
    protected function resolveEntity(string $type, int $id): ?\Illuminate\Database\Eloquent\Model
    {
        $map = [
            'licenses' => \App\Models\License::class,
            'customers' => \App\Models\Customer::class,
            'tickets' => \App\Models\Ticket::class,
            'products' => \App\Models\Product::class,
            'subscriptions' => \App\Models\Subscription::class,
            'invoices' => \App\Models\Invoice::class,
            'devices' => \App\Models\Device::class,
            'api-keys' => \App\Models\ApiKey::class,
        ];

        $class = $map[$type] ?? null;
        if (!$class) return null;

        return $class::find($id);
    }
}
