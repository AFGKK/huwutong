<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CompatTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompatTestController extends Controller
{
    public function __construct(
        protected CompatTestService $compatService
    ) {}

    // ─── 平台管理 ───

    /**
     * 获取平台列表
     */
    public function getPlatforms(Request $request)
    {
        $platforms = $this->compatService->getPlatforms($request->user()->tenant_id);
        return ApiResponse::success($platforms);
    }

    /**
     * 获取平台模板
     */
    public function getPlatformTemplates()
    {
        return ApiResponse::success($this->compatService->getPlatformTemplates());
    }

    /**
     * 从模板初始化平台
     */
    public function initializePlatforms(Request $request)
    {
        $count = $this->compatService->initializeFromTemplates(
            $request->user()->tenant_id,
            $request->input('categories', []),
        );
        return ApiResponse::success(['created_count' => $count], __('app.api.compat_test.created_platforms', ['count' => $count]));
    }

    // ─── 测试套件管理 ───

    /**
     * 获取套件列表
     */
    public function getSuites(Request $request)
    {
        $suites = $this->compatService->getSuites($request->user()->tenant_id);
        return ApiResponse::success($suites);
    }

    /**
     * 创建测试套件
     */
    public function createSuite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|in:integration,browser,api,performance',
            'tags' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.compat_test.validation_failed'), $validator->errors()->toArray());
        }

        $suite = $this->compatService->createSuite($request->user()->tenant_id, $validator->validated());
        return ApiResponse::success($suite, __('app.api.compat_test.suite_created'));
    }

    /**
     * 获取套件详情（含用例）
     */
    public function getSuiteDetail(int $id)
    {
        $suite = \App\Models\CompatibilityTestSuite::with('testCases')->findOrFail($id);
        return ApiResponse::success($suite);
    }

    // ─── 测试用例管理 ───

    /**
     * 添加测试用例
     */
    public function addTestCase(Request $request, int $suiteId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'expected_result' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_critical' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.compat_test.validation_failed'), $validator->errors()->toArray());
        }

        $testCase = $this->compatService->addTestCase($suiteId, $validator->validated());
        return ApiResponse::success($testCase, __('app.api.compat_test.case_added'));
    }

    /**
     * 批量导入测试用例
     */
    public function bulkAddTestCases(Request $request, int $suiteId)
    {
        $validator = Validator::make($request->all(), [
            'cases' => 'required|array|min:1|max:200',
            'cases.*.name' => 'required|string|max:200',
            'cases.*.description' => 'nullable|string',
            'cases.*.expected_result' => 'nullable|string',
            'cases.*.is_critical' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.compat_test.validation_failed'), $validator->errors()->toArray());
        }

        $cases = $this->compatService->bulkAddTestCases($suiteId, $validator->validated()['cases']);
        return ApiResponse::success($cases, __('app.api.compat_test.cases_imported', ['count' => count($cases)]));
    }

    // ─── 测试运行管理 ───

    /**
     * 创建测试运行
     */
    public function createTestRun(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform_ids' => 'required|array|min:1',
            'platform_ids.*' => 'integer|exists:compatibility_platforms,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.compat_test.validation_failed'), $validator->errors()->toArray());
        }

        $run = $this->compatService->createTestRun(
            $request->user()->tenant_id,
            $request->input('platform_ids'),
            $request->user()->id,
        );

        return ApiResponse::success($run, __('app.api.compat_test.run_created'));
    }

    /**
     * 开始测试运行
     */
    public function startTestRun(int $id)
    {
        try {
            $run = $this->compatService->startTestRun($id);
            return ApiResponse::success($run, __('app.api.compat_test.run_started'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('START_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 记录测试结果
     */
    public function recordResult(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'platform_id' => 'required|integer|exists:compatibility_platforms,id',
            'test_case_id' => 'required|integer|exists:compatibility_test_cases,id',
            'result' => 'required|string|in:passed,failed,skipped',
            'error_message' => 'nullable|string',
            'execution_time_ms' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.compat_test.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $record = $this->compatService->recordTestResult(
                $id,
                $request->input('platform_id'),
                $request->input('test_case_id'),
                $request->input('result'),
                $request->input('error_message'),
                $request->input('execution_time_ms'),
                $request->user()->id,
            );
            return ApiResponse::success($record, __('app.api.compat_test.result_recorded'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('RECORD_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 批量记录结果
     */
    public function recordBatchResults(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'results' => 'required|array|min:1',
            'results.*.platform_id' => 'required|integer',
            'results.*.test_case_id' => 'required|integer',
            'results.*.result' => 'required|string|in:passed,failed,skipped',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.compat_test.validation_failed'), $validator->errors()->toArray());
        }

        $count = $this->compatService->recordBatchResults($id, $validator->validated()['results']);
        return ApiResponse::success(['recorded' => $count], __('app.api.compat_test.results_recorded', ['count' => $count]));
    }

    /**
     * 完成测试运行
     */
    public function completeTestRun(int $id)
    {
        try {
            $run = $this->compatService->completeTestRun($id);
            $msg = $run->status === 'passed' ? __('app.api.compat_test.all_passed') : __('app.api.compat_test.has_failures');
            return ApiResponse::success($run, $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('COMPLETE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 获取测试运行详情
     */
    public function getTestRunDetail(int $id)
    {
        $detail = $this->compatService->getTestRunDetail($id);
        return ApiResponse::success($detail);
    }

    /**
     * 获取测试运行历史
     */
    public function getTestRunHistory(Request $request)
    {
        $result = $this->compatService->getTestRunHistory(
            $request->user()->tenant_id,
            $request->only(['status', 'date_from', 'date_to']),
            $request->input('per_page', 20),
        );
        return ApiResponse::success($result);
    }

    // ─── 统计 ───

    /**
     * 获取兼容性测试统计
     */
    public function getStats(Request $request)
    {
        $stats = $this->compatService->getStats($request->user()->tenant_id);
        return ApiResponse::success($stats);
    }
}
