<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RenewalReminderTemplate;
use App\Services\RenewalReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RenewalReminderController extends Controller
{
    public function __construct(
        protected RenewalReminderService $service
    ) {}

    // ─── 模板管理 ───

    public function templates(Request $request)
    {
        return ApiResponse::success(
            $this->service->listTemplates(
                $request->user()->tenant_id,
                $request->only(['channel', 'is_active', 'per_page'])
            )
        );
    }

    public function storeTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'channel' => 'required|string|in:mail,sms,in_app',
            'days_before' => 'required|integer|min:0|max:365',
            'subject' => 'nullable|string|max:200',
            'content' => 'nullable|string',
            'sms_content' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createTemplate($data), 201);
    }

    public function updateTemplate(Request $request, RenewalReminderTemplate $renewalReminderTemplate)
    {
        return ApiResponse::success($this->service->updateTemplate($renewalReminderTemplate, $request->all()));
    }

    public function deleteTemplate(RenewalReminderTemplate $renewalReminderTemplate)
    {
        $this->service->deleteTemplate($renewalReminderTemplate);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 提醒发送 ───

    public function processDue(Request $request)
    {
        return ApiResponse::success(
            $this->service->processDueReminders($request->user()->tenant_id)
        );
    }

    // ─── 发送记录 ───

    public function reminderLogs(Request $request)
    {
        return ApiResponse::success(
            $this->service->listReminderLogs(
                $request->user()->tenant_id,
                $request->only(['status', 'channel', 'subscription_id', 'per_page'])
            )
        );
    }

    // ─── 分析优化 ───

    public function conversionAnalytics(Request $request)
    {
        return ApiResponse::success(
            $this->service->getConversionAnalytics($request->user()->tenant_id)
        );
    }

    public function optimizationSuggestions(Request $request)
    {
        return ApiResponse::success(
            $this->service->getOptimizationSuggestions($request->user()->tenant_id)
        );
    }
}
