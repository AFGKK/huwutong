<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ScheduledNotification;
use App\Services\ScheduledNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduledNotificationController extends Controller
{
    public function __construct(protected ScheduledNotificationService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.scheduled_notification.validation_failed'), $validator->errors()->toArray());
        }

        return ApiResponse::success(
            $this->service->getDashboard($request->input('start_date'), $request->input('end_date'))
        );
    }

    /**
     * 列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getList($request->all()));
    }

    /**
     * 详情
     */
    public function show(int $id): JsonResponse
    {
        $notification = ScheduledNotification::with('creator:id,name,email')->findOrFail($id);
        return ApiResponse::success($notification);
    }

    /**
     * 创建
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'type' => 'required|string|in:' . implode(',', array_keys(config('scheduled-notification.types', []))),
            'channel' => 'required|string|in:' . implode(',', array_keys(config('scheduled-notification.channels', []))),
            'content' => 'required|string|max:10000',
            'rich_content' => 'nullable|string|max:50000',
            'action_url' => 'nullable|string|max:500',
            'action_text' => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date|after_or_equal:now',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.scheduled_notification.validation_failed'), $validator->errors()->toArray());
        }

        $notification = $this->service->create(
            $validator->validated(),
            $request->user()->id
        );

        return ApiResponse::created($notification, __('app.api.scheduled_notification.notification_created'));
    }

    /**
     * 更新
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $notification = ScheduledNotification::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'type' => 'sometimes|string|in:' . implode(',', array_keys(config('scheduled-notification.types', []))),
            'channel' => 'sometimes|string|in:' . implode(',', array_keys(config('scheduled-notification.channels', []))),
            'content' => 'sometimes|string|max:10000',
            'rich_content' => 'nullable|string|max:50000',
            'action_url' => 'nullable|string|max:500',
            'action_text' => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.scheduled_notification.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $notification = $this->service->update($notification, $validator->validated());
            return ApiResponse::success($notification, __('app.api.scheduled_notification.notification_updated'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * 删除
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = ScheduledNotification::findOrFail($id);

        if (in_array($notification->status, ['sending', 'sent', 'partial'])) {
            return ApiResponse::error(__('app.api.scheduled_notification.cannot_delete_sending'), 400);
        }

        $notification->deliveryLogs()->delete();
        $notification->delete();

        return ApiResponse::success(null, __('app.api.scheduled_notification.notification_deleted'));
    }

    /**
     * 发送通知
     */
    public function send(int $id): JsonResponse
    {
        $notification = ScheduledNotification::findOrFail($id);

        try {
            $notification = $this->service->send($notification);
            return ApiResponse::success($notification, __('app.api.scheduled_notification.send_complete'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * 撤销通知
     */
    public function cancel(int $id): JsonResponse
    {
        $notification = ScheduledNotification::findOrFail($id);

        try {
            $notification = $this->service->cancel($notification);
            return ApiResponse::success($notification, __('app.api.scheduled_notification.notification_revoked'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * 预览通知
     */
    public function preview(int $id): JsonResponse
    {
        $notification = ScheduledNotification::findOrFail($id);
        return ApiResponse::success($this->service->preview($notification));
    }

    /**
     * 投递日志
     */
    public function deliveryLogs(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success($this->service->getDeliveryLogs($id, $request->all()));
    }

    /**
     * 计算接收人数
     */
    public function countRecipients(int $id): JsonResponse
    {
        $notification = ScheduledNotification::findOrFail($id);
        return ApiResponse::success([
            'count' => $this->service->countRecipients($notification),
        ]);
    }

    /**
     * 获取配置选项
     */
    public function options(): JsonResponse
    {
        return ApiResponse::success([
            'channels' => config('scheduled-notification.channels'),
            'types' => config('scheduled-notification.types'),
            'templates' => config('scheduled-notification.templates'),
            'sending' => config('scheduled-notification.sending'),
        ]);
    }
}
