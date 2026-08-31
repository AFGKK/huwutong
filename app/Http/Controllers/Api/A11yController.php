<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Services\A11yService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class A11yController extends Controller
{
    public function __construct(
        protected A11yService $a11yService,
    ) {}

    // ── WCAG 2.1 AA 合规准则 ──

    /**
     * 获取 WCAG 2.1 AA 合规准则列表
     */
    public function guidelines()
    {
        return ApiResponse::success($this->a11yService->getGuidelines());
    }

    /**
     * 获取合规统计
     */
    public function stats()
    {
        return ApiResponse::success($this->a11yService->getComplianceStats());
    }

    /**
     * 获取合规声明综合报告
     */
    public function report()
    {
        return ApiResponse::success($this->a11yService->generateReport());
    }

    /**
     * 获取已知限制列表
     */
    public function limitations()
    {
        return ApiResponse::success($this->a11yService->getKnownLimitations());
    }

    /**
     * 对比度检查
     */
    public function checkContrast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foreground' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'background' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        return ApiResponse::success($this->a11yService->checkContrast(
            $request->input('foreground'),
            $request->input('background'),
        ));
    }

    /**
     * 获取/保存用户无障碍偏好
     */
    public function preferences(Request $request)
    {
        $user = $request->user();

        if ($request->isMethod('get')) {
            return ApiResponse::success($this->a11yService->getUserPreferences($user->id));
        }

        $validator = Validator::make($request->all(), [
            'reduced_motion' => 'boolean',
            'high_contrast' => 'boolean',
            'font_size' => 'string|in:small,normal,large,extra_large',
            'screen_reader_optimized' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $prefs = $this->a11yService->saveUserPreferences($user->id, $validator->validated());
        return ApiResponse::success($prefs, __("app.a11y.msg_2171f93e"));
    }

    /**
     * 合规声明页面（WCAG 符合性声明专用）
     */
    public function declaration()
    {
        return ApiResponse::success([
            'title' => __('app.a11y.wcag_compliance_statement'),
            'standard' => 'WCAG 2.1 AA',
            'status' => __('app.a11y.partially_compliant'),
            'last_reviewed' => now()->toDateString(),
            'scope' => __('app.a11y.scope_text'),
            'summary' => $this->a11yService->getComplianceStats(),
            'report' => $this->a11yService->generateReport(),
        ]);
    }

    // ── 无障碍 AI 辅助（原 AccessibilityController 方法） ──

    /**
     * 生成图片 ALT 文本
     */
    public function imageAlt(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'image_url' => 'required|string|max:2000',
        ]);

        $alt = $this->a11yService->generateImageAlt($validated['image_url']);

        return ApiResponse::success([
            'alt_text' => $alt,
            'image_url' => $validated['image_url'],
        ]);
    }

    /**
     * 生成图片详细描述
     */
    public function describeImage(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'image_url' => 'required|string|max:2000',
        ]);

        $result = $this->a11yService->describeImageDetail($validated['image_url']);

        return ApiResponse::success($result);
    }

    /**
     * 生成消息无障碍摘要
     */
    public function messageSummary(int $messageId): \Illuminate\Http\JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);
        $summary = $this->a11yService->summarizeMessage($msg);

        return ApiResponse::success([
            'summary' => $summary,
            'message_id' => $messageId,
        ]);
    }

    /**
     * 生成会话无障碍摘要列表
     */
    public function conversationSummary(int $convId, Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);
        $result = $this->a11yService->summarizeConversation($convId, $limit);

        return ApiResponse::success($result);
    }

    /**
     * 获取无障碍设置默认值
     */
    public function defaultSettings(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'font_size' => 'normal',
            'reduced_motion' => false,
            'high_contrast' => false,
            'screen_reader_optimized' => false,
            'auto_image_alt' => true,
            'message_announcements' => true,
        ]);
    }
}
