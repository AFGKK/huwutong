<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CertificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CertificationController extends Controller
{
    public function __construct(
        protected CertificationService $certService
    ) {}

    // ─── 认证等级管理 ───

    /**
     * 获取所有认证等级
     */
    public function getLevels(Request $request)
    {
        $levels = $this->certService->getLevels(
            $request->user()->tenant_id,
            !$request->boolean('all'),
        );
        return ApiResponse::success($levels);
    }

    /**
     * 创建认证等级
     */
    public function createLevel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:certification_levels,slug',
            'description' => 'nullable|string|max:2000',
            'level_order' => 'nullable|integer|min:0',
            'icon_url' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'passing_score' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        $level = $this->certService->createLevel(
            $request->user()->tenant_id,
            $validator->validated(),
        );

        return ApiResponse::success($level, __('app.api.cert.level_created'));
    }

    /**
     * 更新认证等级
     */
    public function updateLevel(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'level_order' => 'nullable|integer|min:0',
            'icon_url' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'passing_score' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        $level = $this->certService->updateLevel($id, $validator->validated());
        return ApiResponse::success($level, __('app.api.cert.level_updated'));
    }

    // ─── 题库管理 ───

    /**
     * 获取试题列表
     */
    public function getQuestions(Request $request, int $levelId)
    {
        $showAnswers = $request->boolean('show_answers');
        $questions = $this->certService->getQuestions($levelId, !$request->boolean('all'));

        // 不显示正确答案
        if (!$showAnswers) {
            $questions = array_map(function ($q) {
                if (isset($q['options'])) {
                    $q['options'] = array_map(function ($opt) {
                        unset($opt['is_correct']);
                        return $opt;
                    }, $q['options']);
                }
                return $q;
            }, $questions);
        }

        return ApiResponse::success($questions);
    }

    /**
     * 添加试题
     */
    public function addQuestion(Request $request, int $levelId)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
            'type' => 'nullable|string|in:single_choice,multiple_choice,true_false',
            'options' => 'required|array|min:2',
            'options.*.text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
            'explanation' => 'nullable|string|max:2000',
            'points' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        $question = $this->certService->addQuestion($levelId, $validator->validated());
        return ApiResponse::success($question, __('app.api.cert.question_added'));
    }

    /**
     * 批量导入试题
     */
    public function bulkAddQuestions(Request $request, int $levelId)
    {
        $validator = Validator::make($request->all(), [
            'questions' => 'required|array|min:1|max:100',
            'questions.*.question' => 'required|string|max:1000',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.text' => 'required|string',
            'questions.*.options.*.is_correct' => 'required|boolean',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        $questions = $this->certService->bulkAddQuestions(
            $levelId,
            $validator->validated()['questions'],
        );

        return ApiResponse::success($questions, __('app.api.cert.questions_imported', ['count' => count($questions)]));
    }

    // ─── 考试流程 ───

    /**
     * 开始考试
     */
    public function startExam(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'certification_level_id' => 'required|integer|exists:certification_levels,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $devCert = $this->certService->startExam(
                $request->user()->id,
                $request->input('certification_level_id'),
            );
            return ApiResponse::success($devCert, __('app.api.cert.exam_started'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('EXAM_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 获取考试试题
     */
    public function getExamQuestions(Request $request, int $devCertId)
    {
        try {
            $questions = $this->certService->getExamQuestions($devCertId);
            return ApiResponse::success($questions);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('EXAM_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * 提交单题答案
     */
    public function submitAnswer(Request $request, int $devCertId)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer|exists:exam_questions,id',
            'selected_answers' => 'required|array',
            'selected_answers.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $answer = $this->certService->submitAnswer(
                $devCertId,
                $request->input('question_id'),
                $request->input('selected_answers'),
            );
            return ApiResponse::success($answer, __('app.api.cert.answer_submitted'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ANSWER_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 交卷
     */
    public function submitExam(Request $request, int $devCertId)
    {
        try {
            $result = $this->certService->submitExam($devCertId);
            $msg = $result->isPassed() ? __('app.api.cert.exam_passed') : __('app.api.cert.exam_failed');
            return ApiResponse::success($result, $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('SUBMIT_FAILED', $e->getMessage(), 400);
        }
    }

    // ─── 用户认证 ───

    /**
     * 获取当前用户的认证列表
     */
    public function myCertifications(Request $request)
    {
        $certs = $this->certService->getUserCertifications($request->user()->id);
        return ApiResponse::success($certs);
    }

    /**
     * 获取当前用户的认证统计
     */
    public function myStats(Request $request)
    {
        $stats = $this->certService->getUserCertificationStats($request->user()->id);
        return ApiResponse::success($stats);
    }

    // ─── 全局统计 ───

    /**
     * 获取全局统计
     */
    public function globalStats(Request $request)
    {
        $stats = $this->certService->getGlobalStats($request->user()->tenant_id);
        return ApiResponse::success($stats);
    }

    /**
     * 吊销认证
     */
    public function revoke(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $cert = $this->certService->revokeCertification($id, $request->input('reason'));
            return ApiResponse::success($cert, __('app.api.cert.revoked'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REVOKE_FAILED', $e->getMessage(), 400);
        }
    }

    // ─── M3-58 增强: 徽章/目录/验证/权益 ───

    /**
     * 公开证书验证
     */
    public function verifyByNumber(string $certNumber)
    {
        $result = $this->certService->verifyCertificate($certNumber);

        if (!$result) {
            return ApiResponse::error('NOT_FOUND', __('app.api.cert.cert_missing'), 404);
        }

        return ApiResponse::success($result);
    }

    /**
     * 公开认证开发者目录
     */
    public function directory(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $directory = $this->certService->getDirectory($perPage);

        return ApiResponse::success($directory);
    }

    /**
     * 生成徽章 SVG
     */
    public function badgeSvg(int $levelId, ?string $userName = null)
    {
        $level = \App\Models\CertificationLevel::findOrFail($levelId);
        $name = $userName ?? auth()->user()?->name ?? 'Developer';
        $svg = $this->certService->generateBadgeSvg($level->name, $level->color ?? '#0f172a', $name);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * 获取认证权益
     */
    public function getBenefits(int $levelId)
    {
        $benefits = $this->certService->getBenefits($levelId);

        return ApiResponse::success($benefits);
    }

    /**
     * 添加权益
     */
    public function addBenefit(Request $request, int $levelId)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'value' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cert.validation_failed'), $validator->errors()->toArray());
        }

        $benefit = $this->certService->addBenefit($levelId, $request->all());

        return ApiResponse::success($benefit, __('app.api.cert.benefit_added'), 201);
    }

    /**
     * 删除权益
     */
    public function deleteBenefit(int $benefitId)
    {
        $this->certService->deleteBenefit($benefitId);

        return ApiResponse::success(null, __('app.api.cert.benefit_deleted'));
    }
}
