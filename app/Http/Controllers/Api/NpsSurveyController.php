<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\NpsSurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NpsSurveyController extends Controller
{
    public function __construct(protected NpsSurveyService $npsService) {}

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
            return ApiResponse::validationError(__("app.nps_survey.msg_f0a154e5"), $validator->errors()->toArray());
        }

        $data = $this->npsService->getDashboard(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * NPS 报告
     */
    public function report(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.nps_survey.param_validation_failed'), $validator->errors()->toArray());
        }

        $data = $this->npsService->getReport(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return ApiResponse::success($data);
    }

    /**
     * 调查列表
     */
    public function surveys(Request $request): JsonResponse
    {
        $data = $this->npsService->getSurveys($request->all());
        return ApiResponse::success($data);
    }

    /**
     * 反馈列表
     */
    public function responses(Request $request): JsonResponse
    {
        $data = $this->npsService->getResponses($request->all());
        return ApiResponse::success($data);
    }

    /**
     * 发送调查
     */
    public function sendSurvey(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'channel' => 'nullable|string|in:email,in-app,popup',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.nps_survey.param_validation_failed'), $validator->errors()->toArray());
        }

        $survey = $this->npsService->sendSurvey(
            (int) $request->input('user_id'),
            $request->input('channel', 'email')
        );

        if (!$survey) {
            return ApiResponse::error(__("app.nps_survey.msg_170ef082"), 400);
        }

        return ApiResponse::created($survey, __("app.nps_survey.msg_4c78a0dc"));
    }

    /**
     * 提交评分（公开）
     */
    public function submitResponse(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'survey_id' => 'required|integer|exists:nps_surveys,id',
            'score' => 'required|integer|min:0|max:10',
            'feedback' => 'nullable|string|max:2000',
            'best_feature' => 'nullable|string|max:500',
            'improvement' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.nps_survey.param_validation_failed'), $validator->errors()->toArray());
        }

        try {
            $response = $this->npsService->submitResponse(
                (int) $request->input('survey_id'),
                $validator->validated()
            );

            return ApiResponse::success($response, __("app.nps_survey.msg_a08f5623"));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * 趋势数据
     */
    public function trend(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 90);
        $data = $this->npsService->getSnapshotTrend($days);
        return ApiResponse::success($data);
    }

    /**
     * 生成快照
     */
    public function generateSnapshot(): JsonResponse
    {
        $snapshot = $this->npsService->generateDailySnapshot();
        return ApiResponse::success($snapshot, __("app.nps_survey.msg_f2762270"));
    }

    /**
     * 获取可发送调查的用户
     */
    public function eligibleUsers(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 50);
        $users = $this->npsService->getEligibleUsers($limit);
        return ApiResponse::success($users);
    }

    /**
     * 获取配置
     */
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'score_ranges' => config('nps-survey.score_ranges'),
            'trigger' => config('nps-survey.trigger'),
            'questions' => config('nps-survey.questions'),
            'detractor_followup' => config('nps-survey.detractor_followup'),
        ]);
    }
}
