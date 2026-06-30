<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Services\AccessibilityService;
use Illuminate\Http\Request;

class AccessibilityController extends Controller
{
    public function __construct(protected AccessibilityService $a11yService) {}

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
