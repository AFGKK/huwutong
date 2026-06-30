<?php

namespace App\Services;

use App\Models\CompatibilityMatrixResult;
use App\Models\CompatibilityPlatform;
use App\Models\CompatibilityTestCase;
use App\Models\CompatibilityTestResult;
use App\Models\CompatibilityTestRun;
use App\Models\CompatibilityTestSuite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 兼容性测试矩阵服务
 *
 * M3-31
 * 管理平台定义、测试套件/用例、测试运行、矩阵结果
 */
class CompatTestService
{
    // ──────────────────────────────────────────────
    //  平台管理
    // ──────────────────────────────────────────────

    /**
     * 获取所有平台（按类别分组）
     */
    public function getPlatforms(int $tenantId): array
    {
        $platforms = CompatibilityPlatform::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return $platforms->groupBy('category')->toArray();
    }

    /**
     * 获取预定义的平台模板
     */
    public function getPlatformTemplates(): array
    {
        return [
            'php' => [
                ['name' => 'PHP 8.1', 'version' => '8.1', 'label' => 'PHP 8.1'],
                ['name' => 'PHP 8.2', 'version' => '8.2', 'label' => 'PHP 8.2'],
                ['name' => 'PHP 8.3', 'version' => '8.3', 'label' => 'PHP 8.3'],
            ],
            'mysql' => [
                ['name' => 'MySQL 8.0', 'version' => '8.0', 'label' => 'MySQL 8.0'],
                ['name' => 'MySQL 8.4', 'version' => '8.4', 'label' => 'MySQL 8.4'],
                ['name' => 'MariaDB 10.11', 'version' => '10.11', 'label' => 'MariaDB 10.11'],
            ],
            'redis' => [
                ['name' => 'Redis 6.x', 'version' => '6', 'label' => 'Redis 6.x'],
                ['name' => 'Redis 7.x', 'version' => '7', 'label' => 'Redis 7.x'],
            ],
            'browser' => [
                ['name' => 'Chrome', 'version' => 'latest-chrome', 'label' => 'Chrome (最新)'],
                ['name' => 'Firefox', 'version' => 'latest-firefox', 'label' => 'Firefox (最新)'],
                ['name' => 'Safari', 'version' => 'latest-safari', 'label' => 'Safari (最新)'],
                ['name' => 'Edge', 'version' => 'latest-edge', 'label' => 'Edge (最新)'],
            ],
            'os' => [
                ['name' => 'Windows', 'version' => '10/11', 'label' => 'Windows 10/11'],
                ['name' => 'macOS', 'version' => 'latest-macos', 'label' => 'macOS (最新)'],
                ['name' => 'Linux Ubuntu', 'version' => '22.04', 'label' => 'Ubuntu 22.04'],
            ],
        ];
    }

    /**
     * 从模板初始化平台
     */
    public function initializeFromTemplates(int $tenantId, array $categories = []): int
    {
        $templates = $this->getPlatformTemplates();
        $count = 0;

        foreach ($templates as $category => $platforms) {
            if (!empty($categories) && !in_array($category, $categories)) {
                continue;
            }
            foreach ($platforms as $idx => $p) {
                CompatibilityPlatform::updateOrCreate(
                    ['tenant_id' => $tenantId, 'category' => $category, 'version' => $p['version']],
                    [
                        'name' => $p['name'],
                        'label' => $p['label'],
                        'sort_order' => $idx,
                        'is_active' => true,
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    // ──────────────────────────────────────────────
    //  测试套件/用例管理
    // ──────────────────────────────────────────────

    /**
     * 获取测试套件列表（含用例数）
     */
    public function getSuites(int $tenantId): array
    {
        return CompatibilityTestSuite::withCount('testCases')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * 创建测试套件
     */
    public function createSuite(int $tenantId, array $data): CompatibilityTestSuite
    {
        return CompatibilityTestSuite::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'integration',
            'tags' => $data['tags'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * 添加测试用例
     */
    public function addTestCase(int $suiteId, array $data): CompatibilityTestCase
    {
        return CompatibilityTestCase::create([
            'suite_id' => $suiteId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'expected_result' => $data['expected_result'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_critical' => $data['is_critical'] ?? false,
        ]);
    }

    /**
     * 批量导入测试用例
     */
    public function bulkAddTestCases(int $suiteId, array $cases): array
    {
        $created = [];
        DB::transaction(function () use ($suiteId, $cases, &$created) {
            foreach ($cases as $data) {
                $created[] = $this->addTestCase($suiteId, $data);
            }
        });
        return $created;
    }

    // ──────────────────────────────────────────────
    //  测试运行管理
    // ──────────────────────────────────────────────

    /**
     * 创建新的测试运行
     */
    public function createTestRun(int $tenantId, array $platformIds, ?int $userId = null): CompatibilityTestRun
    {
        $reference = 'CTR-' . strtoupper(Str::random(10));

        return DB::transaction(function () use ($tenantId, $platformIds, $userId, $reference) {
            $run = CompatibilityTestRun::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'status' => CompatibilityTestRun::STATUS_PENDING,
                'triggered_by' => $userId ? 'manual' : 'system',
                'triggered_by_user_id' => $userId,
            ]);

            // 创建矩阵行
            foreach ($platformIds as $platformId) {
                CompatibilityMatrixResult::create([
                    'test_run_id' => $run->id,
                    'platform_id' => $platformId,
                    'result' => 'pending',
                ]);
            }

            return $run;
        });
    }

    /**
     * 开始测试运行
     */
    public function startTestRun(int $runId): CompatibilityTestRun
    {
        $run = CompatibilityTestRun::findOrFail($runId);

        $totalCases = CompatibilityTestCase::whereHas('suite', fn ($q) => $q->where('is_active', true))->count();
        $platformCount = $run->matrixResults()->count();

        $run->update([
            'status' => CompatibilityTestRun::STATUS_RUNNING,
            'total_tests' => $totalCases * $platformCount,
            'started_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * 记录单个测试结果
     */
    public function recordTestResult(
        int $runId, int $platformId, int $testCaseId,
        string $result, ?string $errorMessage = null, ?float $execMs = null, ?int $testerUserId = null
    ): CompatibilityTestResult {
        $record = CompatibilityTestResult::updateOrCreate(
            ['test_run_id' => $runId, 'platform_id' => $platformId, 'test_case_id' => $testCaseId],
            [
                'result' => $result,
                'error_message' => $errorMessage,
                'execution_time_ms' => $execMs,
                'tester_user_id' => $testerUserId,
            ],
        );

        // 自动更新矩阵状态
        $this->updateMatrixResult($runId, $platformId);

        return $record;
    }

    /**
     * 批量记录测试结果
     */
    public function recordBatchResults(int $runId, array $results): int
    {
        $count = 0;
        DB::transaction(function () use ($runId, $results, &$count) {
            foreach ($results as $r) {
                $this->recordTestResult(
                    $runId, $r['platform_id'], $r['test_case_id'],
                    $r['result'], $r['error_message'] ?? null,
                    $r['execution_time_ms'] ?? null, $r['tester_user_id'] ?? null,
                );
                $count++;
            }
        });
        return $count;
    }

    /**
     * 完成测试运行
     */
    public function completeTestRun(int $runId): CompatibilityTestRun
    {
        $run = CompatibilityTestRun::findOrFail($runId);

        $passed = CompatibilityTestResult::where('test_run_id', $runId)
            ->where('result', 'passed')->count();
        $failed = CompatibilityTestResult::where('test_run_id', $runId)
            ->where('result', 'failed')->count();
        $skipped = CompatibilityTestResult::where('test_run_id', $runId)
            ->where('result', 'skipped')->count();

        $overallStatus = $failed > 0
            ? CompatibilityTestRun::STATUS_FAILED
            : CompatibilityTestRun::STATUS_PASSED;

        $run->update([
            'status' => $overallStatus,
            'passed_tests' => $passed,
            'failed_tests' => $failed,
            'skipped_tests' => $skipped,
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * 获取测试运行详情（含矩阵）
     */
    public function getTestRunDetail(int $runId): array
    {
        $run = CompatibilityTestRun::with([
            'triggerUser',
            'matrixResults.platform',
            'testResults.testCase',
            'testResults.platform',
            'testResults.tester',
        ])->findOrFail($runId);

        $matrix = [];
        $categories = [];
        $platformResults = $run->testResults->groupBy('platform_id');

        foreach ($run->matrixResults as $mr) {
            $platform = $mr->platform;
            if (!$platform) continue;

            $category = $platform->category;
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }

            $platformData = $platform->toArray();
            $platformData['result'] = $mr->result;
            $platformData['test_results'] = ($platformResults->get($platform->id) ?? collect())->toArray();
            $categories[$category][] = $platformData;
        }

        return [
            'run' => $run,
            'matrix_by_category' => $categories,
            'summary' => [
                'total' => $run->total_tests,
                'passed' => $run->passed_tests,
                'failed' => $run->failed_tests,
                'skipped' => $run->skipped_tests,
                'pass_rate' => $run->passRate(),
            ],
        ];
    }

    /**
     * 获取测试运行历史
     */
    public function getTestRunHistory(int $tenantId, array $filters = [], int $perPage = 20): array
    {
        $query = CompatibilityTestRun::with('triggerUser')
            ->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    // ──────────────────────────────────────────────
    //  矩阵更新辅助
    // ──────────────────────────────────────────────

    protected function updateMatrixResult(int $runId, int $platformId): void
    {
        $allResults = CompatibilityTestResult::where('test_run_id', $runId)
            ->where('platform_id', $platformId)
            ->get();

        $failed = $allResults->where('result', 'failed')->count();
        $passed = $allResults->where('result', 'passed')->count();
        $pending = $allResults->where('result', 'pending')->count();

        $status = 'passed';
        if ($pending > 0) {
            $status = 'running';
        }
        if ($failed > 0) {
            $status = 'failed';
        }

        CompatibilityMatrixResult::where('test_run_id', $runId)
            ->where('platform_id', $platformId)
            ->update(['result' => $status]);
    }

    // ──────────────────────────────────────────────
    //  统计
    // ──────────────────────────────────────────────

    /**
     * 获取兼容性测试全局统计
     */
    public function getStats(int $tenantId): array
    {
        $platforms = CompatibilityPlatform::where('tenant_id', $tenantId)->count();
        $suites = CompatibilityTestSuite::where('tenant_id', $tenantId)->count();
        $cases = CompatibilityTestCase::whereHas('suite', fn ($q) => $q->where('tenant_id', $tenantId))->count();

        $runs = CompatibilityTestRun::where('tenant_id', $tenantId);
        $totalRuns = (clone $runs)->count();
        $passedRuns = (clone $runs)->where('status', CompatibilityTestRun::STATUS_PASSED)->count();
        $failedRuns = (clone $runs)->where('status', CompatibilityTestRun::STATUS_FAILED)->count();

        $lastRun = (clone $runs)->whereIn('status', [
            CompatibilityTestRun::STATUS_PASSED,
            CompatibilityTestRun::STATUS_FAILED,
        ])->latest()->first();

        return [
            'total_platforms' => $platforms,
            'total_suites' => $suites,
            'total_cases' => $cases,
            'total_runs' => $totalRuns,
            'passed_runs' => $passedRuns,
            'failed_runs' => $failedRuns,
            'last_run' => $lastRun ? [
                'id' => $lastRun->id,
                'reference' => $lastRun->reference,
                'status' => $lastRun->status,
                'pass_rate' => $lastRun->passRate(),
                'completed_at' => $lastRun->completed_at,
            ] : null,
        ];
    }
}
