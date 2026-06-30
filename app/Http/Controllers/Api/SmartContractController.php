<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LicenseContract;
use App\Models\LicenseContractAssignment;
use App\Services\SmartContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmartContractController extends Controller
{
    public function __construct(
        protected SmartContractService $contractService
    ) {}

    // ─── 概览 ───

    /**
     * 仪表盘
     */
    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->contractService->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 评估趋势
     */
    public function trends(Request $request)
    {
        $days = min((int)$request->input('days', 7), 90);
        return ApiResponse::success(
            $this->contractService->getEvaluationTrends($request->user()->tenant_id, $days)
        );
    }

    /**
     * 获取合约类型选项
     */
    public function types()
    {
        return ApiResponse::success([
            'contract_types' => LicenseContract::CONTRACT_TYPES,
            'evaluation_modes' => LicenseContract::EVALUATION_MODES,
            'condition_types' => LicenseContract::CONDITION_TYPES,
        ]);
    }

    // ─── 合约管理 ───

    /**
     * 合约列表
     */
    public function contracts(Request $request)
    {
        return ApiResponse::paginated(
            $this->contractService->getContracts(
                array_merge($request->all(), ['tenant_id' => $request->user()->tenant_id]),
                (int)$request->input('per_page', 20)
            )
        );
    }

    /**
     * 合约详情
     */
    public function showContract(LicenseContract $contract)
    {
        $contract->loadCount('assignments');
        return ApiResponse::success($contract);
    }

    /**
     * 创建合约
     */
    public function storeContract(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:license_contracts,slug',
            'contract_type' => 'required|string|in:' . implode(',', array_keys(LicenseContract::CONTRACT_TYPES)),
            'description' => 'nullable|string|max:500',
            'conditions' => 'required|array|min:1',
            'conditions.*.type' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'actions' => 'nullable|array',
            'evaluation_mode' => 'nullable|in:all,any,custom',
            'custom_expression' => 'nullable|string|max:500',
            'grant_template' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        $contract = $this->contractService->createContract($data);

        return ApiResponse::success($contract, '合约创建成功', 201);
    }

    /**
     * 更新合约
     */
    public function updateContract(Request $request, LicenseContract $contract)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'conditions' => 'nullable|array|min:1',
            'conditions.*.type' => 'required|string',
            'actions' => 'nullable|array',
            'evaluation_mode' => 'nullable|in:all,any,custom',
            'custom_expression' => 'nullable|string|max:500',
            'grant_template' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $contract = $this->contractService->updateContract($contract, $request->all());

        return ApiResponse::success($contract, '合约更新成功');
    }

    /**
     * 删除合约
     */
    public function destroyContract(LicenseContract $contract)
    {
        $deleted = $this->contractService->deleteContract($contract);

        if (!$deleted) {
            return ApiResponse::error('SYSTEM_CONTRACT', '系统合约不可删除', 403);
        }

        return ApiResponse::success(null, '合约已删除');
    }

    /**
     * 播种系统合约
     */
    public function seedContracts(Request $request)
    {
        $count = $this->contractService->seedSystemContracts($request->user()->tenant_id);
        return ApiResponse::success(['seeded' => $count], "已播种 {$count} 条系统合约");
    }

    // ─── 合约分配管理 ───

    /**
     * 合约的分配列表
     */
    public function assignments(int $contractId)
    {
        return ApiResponse::success(
            $this->contractService->getAssignments($contractId)
        );
    }

    /**
     * 创建/更新合约分配
     */
    public function storeAssignment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contract_id' => 'required|exists:license_contracts,id',
            'assignable_type' => 'required|string',
            'assignable_id' => 'required|integer',
            'parameters' => 'nullable|array',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'is_enabled' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $assignment = $this->contractService->assignContract($request->all());

        return ApiResponse::success($assignment, '合约分配成功', 201);
    }

    /**
     * 更新合约分配
     */
    public function updateAssignment(Request $request, LicenseContractAssignment $assignment)
    {
        $validator = Validator::make($request->all(), [
            'parameters' => 'nullable|array',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'is_enabled' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $assignment = $this->contractService->updateAssignment($assignment, $request->all());

        return ApiResponse::success($assignment, '合约分配已更新');
    }

    /**
     * 删除合约分配
     */
    public function destroyAssignment(LicenseContractAssignment $assignment)
    {
        $this->contractService->removeAssignment($assignment);
        return ApiResponse::success(null, '合约分配已移除');
    }

    /**
     * 获取实体的合约分配
     */
    public function entityAssignments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignable_type' => 'required|string',
            'assignable_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        return ApiResponse::success(
            $this->contractService->getEntityAssignments(
                $request->input('assignable_type'),
                (int)$request->input('assignable_id')
            )
        );
    }

    // ─── 合约评估 ───

    /**
     * 执行合约评估（管理端手动触发）
     */
    public function evaluateContract(Request $request, LicenseContract $contract)
    {
        $context = [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'user_roles' => $request->user()->getRoleNames()->toArray(),
            'request_ip' => $request->ip(),
            'current_time' => now()->format('H:i'),
            'current_day' => (int)now()->format('N'),
            'evaluated_by' => 'admin',
        ];

        // 合并请求中的上下文
        $customContext = $request->input('context', []);
        $context = array_merge($context, $customContext);

        $result = $this->contractService->evaluateContract($contract, $context);

        return ApiResponse::success($result);
    }

    /**
     * 为指定实体执行全部合约评估
     */
    public function evaluateEntity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignable_type' => 'required|string',
            'assignable_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $context = [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'user_roles' => $request->user()->getRoleNames()->toArray(),
            'request_ip' => $request->ip(),
            'current_time' => now()->format('H:i'),
            'current_day' => (int)now()->format('N'),
        ];

        $customContext = $request->input('context', []);
        $context = array_merge($context, $customContext);

        $result = $this->contractService->evaluateForEntity(
            $request->input('assignable_type'),
            (int)$request->input('assignable_id'),
            $context
        );

        return ApiResponse::success($result);
    }

    // ─── 评估日志 ───

    /**
     * 评估日志列表
     */
    public function evaluationLogs(Request $request)
    {
        $query = \App\Models\LicenseContractEvaluationLog::with('contract:id,name,slug')
            ->where('tenant_id', $request->user()->tenant_id);

        if (!empty($request->input('result'))) {
            $query->where('result', $request->input('result'));
        }
        if (!empty($request->input('contract_id'))) {
            $query->where('contract_id', $request->input('contract_id'));
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate((int)$request->input('per_page', 20))
            ->toArray();

        return ApiResponse::paginated($logs);
    }
}
