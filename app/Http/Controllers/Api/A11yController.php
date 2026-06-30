<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\A11yService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class A11yController extends Controller
{
    public function __construct(
        protected A11yService $a11yService,
    ) {}

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
        return ApiResponse::success($prefs, '无障碍偏好已保存');
    }

    /**
     * 合规声明页面（WCAG 符合性声明专用）
     */
    public function declaration()
    {
        return ApiResponse::success([
            'title' => 'WCAG 2.1 AA 符合性声明',
            'standard' => 'WCAG 2.1 AA',
            'status' => '部分符合',
            'last_reviewed' => now()->toDateString(),
            'scope' => '管理后台 SPA + 客户门户',
            'summary' => $this->a11yService->getComplianceStats(),
            'report' => $this->a11yService->generateReport(),
        ]);
    }
}
