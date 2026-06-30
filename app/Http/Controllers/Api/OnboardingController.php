<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboarding,
    ) {}

    // ─── 注册向导 ───

    /**
     * 获取 Onboarding 完整仪表盘
     * GET /api/onboarding/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->onboarding->getDashboard($request->user());
        return ApiResponse::success($data);
    }

    /**
     * 获取当前步骤
     * GET /api/onboarding/step
     */
    public function currentStep(Request $request): JsonResponse
    {
        $step = $this->onboarding->getCurrentStep($request->user());
        return ApiResponse::success($step);
    }

    /**
     * 完成步骤
     * POST /api/onboarding/step/{step}
     */
    public function completeStep(Request $request, string $step): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'tenant_name' => 'sometimes|string|max:255',
            'product_name' => 'sometimes|string|max:255',
            'product_description' => 'nullable|string|max:1000',
            'key_name' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        try {
            $result = $this->onboarding->completeStep(
                $request->user(),
                $step,
                $validator->validated()
            );
            return ApiResponse::success($result, '步骤已完成');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('INVALID_STEP', $e->getMessage(), 422);
        }
    }

    /**
     * 跳过后勤
     * POST /api/onboarding/skip
     */
    public function skip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:200',
        ]);

        $this->onboarding->skipOnboarding(
            $request->user(),
            $validator->validated()['reason'] ?? null
        );

        return ApiResponse::success(null, '已跳过后勤');
    }

    // ─── 快速启动清单 ───

    /**
     * 获取快速启动清单
     * GET /api/quick-start
     */
    public function quickStartItems(Request $request): JsonResponse
    {
        $items = $this->onboarding->getQuickStartItems($request->user());
        return ApiResponse::success($items);
    }

    /**
     * 标记快速启动项目完成
     * POST /api/quick-start/{itemKey}/complete
     */
    public function completeQuickStartItem(Request $request, string $itemKey): JsonResponse
    {
        try {
            $item = $this->onboarding->completeQuickStartItem($request->user(), $itemKey);
            return ApiResponse::success($item, '任务已完成');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('未找到该任务');
        }
    }

    // ─── 入门教程 ───

    /**
     * 获取教程列表
     * GET /api/tutorials
     */
    public function tutorials(Request $request): JsonResponse
    {
        $tutorials = $this->onboarding->getUserTutorialProgress($request->user());
        return ApiResponse::success($tutorials);
    }

    /**
     * 获取教程详情
     * GET /api/tutorials/{slug}
     */
    public function showTutorial(string $slug): JsonResponse
    {
        $tutorial = $this->onboarding->getTutorial($slug);
        if (!$tutorial) {
            return ApiResponse::notFound('未找到该教程');
        }
        return ApiResponse::success($tutorial);
    }

    /**
     * 更新教程进度
     * POST /api/tutorials/{tutorialId}/progress
     */
    public function updateTutorialProgress(Request $request, int $tutorialId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'step' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $progress = $this->onboarding->updateTutorialProgress(
            $request->user(),
            $tutorialId,
            (int) $request->input('step')
        );

        return ApiResponse::success($progress, '进度已更新');
    }

    /**
     * 重置 Onboarding（重新开始）
     * POST /api/onboarding/reset
     */
    public function resetOnboarding(Request $request): JsonResponse
    {
        $user = $request->user();

        // 删除旧的进度
        \App\Models\UserOnboardingProgress::where('user_id', $user->id)->delete();
        \App\Models\QuickStartItem::where('user_id', $user->id)->delete();
        \App\Models\UserTutorialProgress::where('user_id', $user->id)->delete();

        $user->update([
            'onboarding_completed' => false,
            'onboarding_skipped_at' => null,
            'onboarding_skip_reason' => null,
        ]);

        $data = $this->onboarding->getDashboard($user);
        return ApiResponse::success($data, 'Onboarding 已重置');
    }
}
