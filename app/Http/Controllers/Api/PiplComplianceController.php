<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CrossBorderTransfer;
use App\Models\Dpia;
use App\Models\PersonalDataInventory;
use App\Services\PiplComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PIPL 个人信息保护法合规控制器 (M3-33b)
 */
class PiplComplianceController extends Controller
{
    public function __construct(
        protected PiplComplianceService $piplService,
    ) {}

    // ─── 个人信息分类分级 ───

    /**
     * 扫描并自动分类字段
     *
     * POST /api/pipl/scan
     */
    public function scan(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'connection' => 'nullable|string',
        ]);

        $tenantId = $httpRequest->user()->tenant_id ?? 1;
        $result = $this->piplService->scanAndClassify($tenantId, $validated['connection'] ?? null);

        return ApiResponse::success($result, __('app.api.pipl_compliance.scan_complete'));
    }

    /**
     * 个人信息清单列表
     *
     * GET /api/pipl/inventory
     */
    public function inventoryIndex(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'table_name' => 'nullable|string',
            'category' => 'nullable|in:person,general,sensitive,private',
            'classification' => 'nullable|in:L1,L2,L3,L4',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PersonalDataInventory::query();

        if (! empty($validated['table_name'])) {
            $query->where('table_name', $validated['table_name']);
        }
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (! empty($validated['classification'])) {
            $query->where('classification', $validated['classification']);
        }

        $items = $query->orderBy('table_name')->orderBy('field_name')
            ->paginate($validated['per_page'] ?? 50);

        return ApiResponse::success($items);
    }

    /**
     * 更新清单条目
     *
     * PUT /api/pipl/inventory/{inventory}
     */
    public function inventoryUpdate(Request $httpRequest, PersonalDataInventory $inventory): JsonResponse
    {
        $validated = $httpRequest->validate([
            'category' => 'nullable|in:person,general,sensitive,private',
            'classification' => 'nullable|in:L1,L2,L3,L4',
            'purpose' => 'nullable|string|max:500',
            'retention_days' => 'nullable|string|max:20',
            'is_required' => 'nullable|boolean',
            'is_exportable' => 'nullable|boolean',
            'is_deletable' => 'nullable|boolean',
            'status' => 'nullable|in:active,archived',
        ]);

        $inventory->update($validated);

        return ApiResponse::success($inventory->fresh(), __('app.api.pipl_compliance.updated'));
    }

    /**
     * 批量更新清单
     *
     * POST /api/pipl/inventory/batch-update
     */
    public function inventoryBatchUpdate(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:personal_data_inventories,id',
            'data' => 'required|array',
            'data.category' => 'nullable|in:person,general,sensitive,private',
            'data.classification' => 'nullable|in:L1,L2,L3,L4',
            'data.status' => 'nullable|in:active,archived',
        ]);

        PersonalDataInventory::whereIn('id', $validated['ids'])
            ->update($validated['data']);

        return ApiResponse::success([], __('app.api.pipl_compliance.batch_updated'));
    }

    // ─── 跨境数据传输 ───

    /**
     * 跨境传输列表
     *
     * GET /api/pipl/cross-border-transfers
     */
    public function crossBorderIndex(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'status' => 'nullable|in:active,expired,revoked',
            'recipient_country' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CrossBorderTransfer::with(['reviewer']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['recipient_country'])) {
            $query->where('recipient_country', 'like', "%{$validated['recipient_country']}%");
        }

        $transfers = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success($transfers);
    }

    /**
     * 创建跨境传输记录
     *
     * POST /api/pipl/cross-border-transfers
     */
    public function storeCrossBorder(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'data_category' => 'required|string|max:50',
            'recipient_country' => 'required|string|max:100',
            'recipient_name' => 'required|string|max:255',
            'recipient_purpose' => 'required|string|max:500',
            'transfer_method' => 'required|in:api,sdk,manual,cloud',
            'legal_basis' => 'required|in:consent,standard_clauses,adequacy,safe_harbor,other',
            'security_measures' => 'nullable|string',
            'impact_assessment' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $httpRequest->user()->tenant_id;

        $transfer = $this->piplService->createCrossBorderTransfer($validated);

        return ApiResponse::success($transfer, __('app.api.pipl_compliance.cross_border_recorded'));
    }

    /**
     * 更新跨境传输
     *
     * PUT /api/pipl/cross-border-transfers/{transfer}
     */
    public function updateCrossBorder(Request $httpRequest, CrossBorderTransfer $transfer): JsonResponse
    {
        $validated = $httpRequest->validate([
            'data_category' => 'string|max:50',
            'recipient_country' => 'string|max:100',
            'recipient_name' => 'string|max:255',
            'recipient_purpose' => 'string|max:500',
            'transfer_method' => 'in:api,sdk,manual,cloud',
            'legal_basis' => 'in:consent,standard_clauses,adequacy,safe_harbor,other',
            'security_measures' => 'nullable|string',
            'status' => 'in:active,expired,revoked',
        ]);

        $transfer->update($validated);

        return ApiResponse::success($transfer->fresh(), __('app.api.pipl_compliance.updated'));
    }

    /**
     * 审核跨境传输
     *
     * POST /api/pipl/cross-border-transfers/{transfer}/review
     */
    public function reviewCrossBorder(Request $httpRequest, CrossBorderTransfer $transfer): JsonResponse
    {
        $validated = $httpRequest->validate([
            'impact_assessment' => 'required|string',
        ]);

        $result = $this->piplService->reviewCrossBorderTransfer(
            $transfer->id,
            $validated['impact_assessment'],
            $httpRequest->user()->id
        );

        return ApiResponse::success($result, __('app.api.pipl_compliance.assessment_complete'));
    }

    // ─── DPIA 数据保护影响评估 ───

    /**
     * DPIA 列表
     *
     * GET /api/pipl/dpias
     */
    public function dpiaIndex(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'status' => 'nullable|in:draft,in_progress,completed,archived',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Dpia::with(['creator']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $dpias = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success($dpias);
    }

    /**
     * 创建 DPIA
     *
     * POST /api/pipl/dpias
     */
    public function storeDpia(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'involved_data_categories' => 'nullable|array',
            'involved_data_categories.*' => 'string',
            'stakeholders' => 'nullable|array',
            'stakeholders.*' => 'string',
        ]);

        $validated['tenant_id'] = $httpRequest->user()->tenant_id;

        $dpia = $this->piplService->createDpia($validated, $httpRequest->user()->id);

        return ApiResponse::success($dpia, __('app.api.pipl_compliance.dpia_created'));
    }

    /**
     * DPIA 详情
     *
     * GET /api/pipl/dpias/{dpia}
     */
    public function showDpia(Dpia $dpia): JsonResponse
    {
        $dpia->load(['creator']);
        return ApiResponse::success($dpia);
    }

    /**
     * 更新 DPIA
     *
     * PUT /api/pipl/dpias/{dpia}
     */
    public function updateDpia(Request $httpRequest, Dpia $dpia): JsonResponse
    {
        $validated = $httpRequest->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'necessity_assessment' => 'nullable|string',
            'risk_assessment' => 'nullable|string',
            'mitigation_measures' => 'nullable|string',
            'conclusion' => 'nullable|string',
            'involved_data_categories' => 'nullable|array',
            'involved_data_categories.*' => 'string',
            'stakeholders' => 'nullable|array',
            'stakeholders.*' => 'string',
            'status' => 'in:draft,in_progress,completed,archived',
        ]);

        $dpia->update($validated);

        return ApiResponse::success($dpia->fresh(), __('app.api.pipl_compliance.dpia_updated'));
    }

    /**
     * 完成 DPIA
     *
     * POST /api/pipl/dpias/{dpia}/complete
     */
    public function completeDpia(Request $httpRequest, Dpia $dpia): JsonResponse
    {
        $validated = $httpRequest->validate([
            'necessity_assessment' => 'required|string',
            'risk_assessment' => 'required|string',
            'mitigation_measures' => 'required|string',
            'conclusion' => 'required|string',
        ]);

        $result = $this->piplService->completeDpia($dpia->id, $validated);

        return ApiResponse::success($result, __('app.api.pipl_compliance.dpia_assessment_done'));
    }

    // ─── 统计 ───

    /**
     * PIPL 合规统计
     *
     * GET /api/pipl/stats
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->piplService->getStats());
    }

    /**
     * 敏感字段定义
     *
     * GET /api/pipl/sensitive-fields
     */
    public function sensitiveFields(): JsonResponse
    {
        return ApiResponse::success($this->piplService->getSensitiveFieldDefinitions());
    }

    // ═══════════════ M3-33b 增强 ═══════════════

    /**
     * DPO 信息
     *
     * GET /api/pipl/dpo
     */
    public function getDpo(): JsonResponse
    {
        return ApiResponse::success($this->piplService->getDpoInfo());
    }

    /**
     * 更新 DPO 信息
     *
     * PUT /api/pipl/dpo
     */
    public function updateDpo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:50',
            'contact_info' => 'nullable|string|max:500',
        ]);

        return ApiResponse::success(
            $this->piplService->updateDpoInfo($validated),
            __('app.api.pipl_compliance.dpo_updated')
        );
    }

    /**
     * 未成年人保护检查
     *
     * POST /api/pipl/check-minor
     */
    public function checkMinor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'birthday' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:150',
        ]);

        return ApiResponse::success(
            $this->piplService->checkMinorProtection($validated)
        );
    }

    /**
     * 创建泄露上报
     *
     * POST /api/pipl/breach-notifications
     */
    public function storeBreach(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'description' => 'required|string',
            'affected_count' => 'nullable|integer|min:0',
            'affected_data_categories' => 'nullable|array',
            'affected_data_categories.*' => 'string',
            'cause' => 'nullable|string',
            'containment_measures' => 'nullable|string',
        ]);

        $result = $this->piplService->createBreachNotification(
            $validated,
            $request->user()->id
        );

        return ApiResponse::success($result, __('app.api.pipl_compliance.breach_reported'));
    }

    /**
     * 增强统计
     *
     * GET /api/pipl/enhanced-stats
     */
    public function enhancedStats(): JsonResponse
    {
        return ApiResponse::success($this->piplService->getEnhancedStats());
    }
}
