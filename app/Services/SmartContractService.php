<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseContract;
use App\Models\LicenseContractAssignment;
use App\Models\LicenseContractEvaluationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 智能合约式授权服务
 *
 * 管理授权合约的生命周期：创建、分配、评估、日志
 */
class SmartContractService
{
    public function __construct(
        protected ContractConditionEngine $conditionEngine
    ) {}

    // ─── 合约管理 ───

    /**
     * 获取合约列表
     */
    public function getContracts(array $filters = [], int $perPage = 20): array
    {
        $query = LicenseContract::query();

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['contract_type'])) {
            $query->where('contract_type', $filters['contract_type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        $contracts = $query->withCount('assignments')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->toArray();

        return $contracts;
    }

    /**
     * 创建合约
     */
    public function createContract(array $data): LicenseContract
    {
        if (empty($data['slug'])) {
            $slug = str($data['name'])->slug('_')->value();
            $data['slug'] = $slug ?: preg_replace('/[^a-zA-Z0-9_]+/', '_', $data['name']);
        }

        $contract = LicenseContract::create($data);

        Log::info('[SmartContract] 合约创建', [
            'contract_id' => $contract->id,
            'slug' => $contract->slug,
            'type' => $contract->contract_type,
        ]);

        return $contract;
    }

    /**
     * 更新合约
     */
    public function updateContract(LicenseContract $contract, array $data): LicenseContract
    {
        if ($contract->is_system) {
            $allowed = ['is_active', 'priority', 'description'];
            $data = array_intersect_key($data, array_flip($allowed));
        }

        $data['version'] = $contract->version + 1;
        $contract->update($data);

        Log::info('[SmartContract] 合约更新', [
            'contract_id' => $contract->id,
            'version' => $contract->version,
        ]);

        return $contract->fresh();
    }

    /**
     * 删除合约
     */
    public function deleteContract(LicenseContract $contract): bool
    {
        if ($contract->is_system) {
            return false;
        }
        return $contract->delete();
    }

    /**
     * 播种系统合约
     */
    public function seedSystemContracts(?int $tenantId = null): int
    {
        $count = 0;
        foreach (LicenseContract::getSystemContracts() as $contractData) {
            $exists = LicenseContract::where('slug', $contractData['slug'])
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->exists();

            if (!$exists) {
                $contractData['tenant_id'] = $tenantId ?? 1;
                $this->createContract($contractData);
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取合约类型选项
     */
    public function getContractTypes(): array
    {
        return LicenseContract::CONTRACT_TYPES;
    }

    // ─── 合约分配 ───

    /**
     * 获取合约的所有分配
     */
    public function getAssignments(int $contractId): array
    {
        return LicenseContractAssignment::where('contract_id', $contractId)
            ->with('contract:id,name,slug')
            ->orderBy('priority')
            ->get()
            ->toArray();
    }

    /**
     * 分配合约到实体
     */
    public function assignContract(array $data): LicenseContractAssignment
    {
        $assignment = LicenseContractAssignment::updateOrCreate(
            [
                'contract_id' => $data['contract_id'],
                'assignable_type' => $data['assignable_type'],
                'assignable_id' => $data['assignable_id'],
            ],
            $data
        );

        Log::info('[SmartContract] 合约分配', [
            'contract_id' => $assignment->contract_id,
            'assignable' => "{$assignment->assignable_type}#{$assignment->assignable_id}",
        ]);

        return $assignment;
    }

    /**
     * 更新合约分配
     */
    public function updateAssignment(LicenseContractAssignment $assignment, array $data): LicenseContractAssignment
    {
        $assignment->update($data);
        return $assignment->fresh();
    }

    /**
     * 删除合约分配
     */
    public function removeAssignment(LicenseContractAssignment $assignment): bool
    {
        return $assignment->delete();
    }

    /**
     * 获取实体的活跃合约分配
     */
    public function getEntityAssignments(string $entityType, int $entityId): array
    {
        return LicenseContractAssignment::enabled()
            ->effective()
            ->where('assignable_type', $entityType)
            ->where('assignable_id', $entityId)
            ->with('contract')
            ->orderBy('priority')
            ->get()
            ->toArray();
    }

    // ─── 合约评估 ───

    /**
     * 为指定实体评估所有关联的授权合约
     *
     * @return array ['granted' => bool, 'evaluations' => [...], 'summary' => [...]]
     */
    public function evaluateForEntity(string $entityType, int $entityId, array $context = []): array
    {
        $assignments = $this->getEntityAssignments($entityType, $entityId);

        if (empty($assignments)) {
            return [
                'granted' => true,
                'evaluations' => [],
                'summary' => ['applied' => 0, 'granted' => 0, 'denied' => 0],
            ];
        }

        $evaluations = [];
        $grantedCount = 0;
        $deniedCount = 0;

        foreach ($assignments as $assignment) {
            $contract = $assignment['contract'] ?? null;
            if (!$contract || !$contract['is_active']) continue;

            $contractModel = LicenseContract::find($contract['id']);
            if (!$contractModel) continue;

            // 合并上下文（分配参数优先）
            $mergedContext = array_merge(
                $context,
                ['parameters' => $assignment['parameters'] ?? []],
                ['assignment' => $assignment]
            );

            // 执行条件评估
            $evaluationResult = $this->conditionEngine->evaluate($contractModel, $mergedContext);
            $granted = $evaluationResult['granted'];

            // 构建日志上下文
            $evaluation = [
                'contract_id' => $contract['id'],
                'contract_name' => $contract['name'],
                'contract_slug' => $contract['slug'],
                'granted' => $granted,
                'evaluation_mode' => $contractModel->evaluation_mode,
                'conditions_results' => $evaluationResult['conditions_results'] ?? [],
                'evaluation_time_ms' => $evaluationResult['evaluation_time_ms'] ?? 0,
            ];

            // 记录评估日志
            $this->logEvaluation($contractModel, $entityType, $entityId, $evaluation, $mergedContext);

            $evaluations[] = $evaluation;

            if ($granted) {
                $grantedCount++;
            } else {
                $deniedCount++;
            }
        }

        // 如果评估模式是 any（默认所有合约都必须通过），只要有一条拒绝即为拒绝
        $allGranted = $deniedCount === 0;

        return [
            'granted' => $allGranted,
            'evaluations' => $evaluations,
            'summary' => [
                'applied' => count($evaluations),
                'granted' => $grantedCount,
                'denied' => $deniedCount,
            ],
        ];
    }

    /**
     * 评估单个合约
     */
    public function evaluateContract(LicenseContract $contract, array $context): array
    {
        $result = $this->conditionEngine->evaluate($contract, $context);

        return [
            'contract_id' => $contract->id,
            'contract_name' => $contract->name,
            'contract_slug' => $contract->slug,
            'granted' => $result['granted'],
            'conditions_results' => $result['conditions_results'] ?? [],
            'evaluation_time_ms' => $result['evaluation_time_ms'] ?? 0,
        ];
    }

    /**
     * 记录评估日志
     */
    protected function logEvaluation(
        LicenseContract $contract,
        string $entityType,
        int $entityId,
        array $evaluation,
        array $context
    ): void {
        try {
            $conditionsResults = $evaluation['conditions_results'] ?? [];
            $matchedConditions = array_values(array_filter($conditionsResults, fn($r) => $r['matched']));
            $failedConditions = array_values(array_filter($conditionsResults, fn($r) => !$r['matched']));

            LicenseContractEvaluationLog::create([
                'tenant_id' => $context['tenant_id'] ?? $contract->tenant_id,
                'contract_id' => $contract->id,
                'contract_slug' => $contract->slug,
                'contract_name' => $contract->name,
                'evaluation_mode' => $contract->evaluation_mode,
                'evaluatable_type' => $entityType,
                'evaluatable_id' => $entityId,
                'result' => $evaluation['granted'] ? 'granted' : 'denied',
                'conditions_results' => $conditionsResults,
                'matched_conditions' => $matchedConditions,
                'failed_conditions' => $failedConditions,
                'reason' => $evaluation['granted'] ? '所有条件满足' : '存在不满足的条件',
                'context_data' => $context,
                'source_ip' => request()->ip(),
                'evaluation_time_ms' => $evaluation['evaluation_time_ms'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SmartContract] 记录评估日志失败', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── 统计 ───

    /**
     * 获取概览数据
     */
    public function getDashboard(?int $tenantId = null): array
    {
        $query = LicenseContract::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        $logQuery = LicenseContractEvaluationLog::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        return [
            'total_contracts' => (clone $query)->count(),
            'active_contracts' => (clone $query)->where('is_active', true)->count(),
            'system_contracts' => (clone $query)->where('is_system', true)->count(),
            'total_evaluations' => (clone $logQuery)->count(),
            'today_evaluations' => (clone $logQuery)->whereDate('created_at', today())->count(),
            'granted_count' => (clone $logQuery)->where('result', 'granted')->count(),
            'denied_count' => (clone $logQuery)->where('result', 'denied')->count(),
            'by_type' => (clone $query)->selectRaw('contract_type, COUNT(*) as cnt')
                ->groupBy('contract_type')->pluck('cnt', 'contract_type')->toArray(),
        ];
    }

    /**
     * 获取评估趋势
     */
    public function getEvaluationTrends(?int $tenantId = null, int $days = 7): array
    {
        $logs = LicenseContractEvaluationLog::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, result, COUNT(*) as cnt')
            ->groupBy('date', 'result')
            ->orderBy('date')
            ->get();

        $trends = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $granted = 0;
            $denied = 0;
            foreach ($logs as $log) {
                if ($log['date'] === $date) {
                    if ($log['result'] === 'granted') $granted = $log['cnt'];
                    else $denied = $log['cnt'];
                }
            }
            $trends[] = [
                'date' => $date,
                'granted' => $granted,
                'denied' => $denied,
                'total' => $granted + $denied,
            ];
        }

        return $trends;
    }
}
