<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DataProcessingAgreement;
use App\Models\DpaSignature;
use App\Models\GdprDataRequest;
use App\Services\GdprComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * GDPR 合规管理控制器 (M3-33)
 */
class GdprComplianceController extends Controller
{
    public function __construct(
        protected GdprComplianceService $gdprService,
    ) {}

    // ─── 数据主体请求 (DSR) ───

    /**
     * 提交 DSR 请求（用户侧）
     *
     * POST /api/gdpr/requests
     */
    public function submitRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:access,export,rectification,erasure,restrict,portability,object',
            'reason' => 'nullable|string|max:1000',
            'request_data' => 'nullable|array',
        ]);

        $gdprRequest = $this->gdprService->submitRequest(
            $request->user()->id,
            $validated['type'],
            $validated['reason'] ?? null,
            $validated['request_data'] ?? []
        );

        return ApiResponse::success($this->formatRequest($gdprRequest), 'DSR 请求已提交');
    }

    /**
     * 用户查看自己的请求列表
     *
     * GET /api/gdpr/my-requests
     */
    public function myRequests(Request $httpRequest): JsonResponse
    {
        $requests = GdprDataRequest::where('user_id', $httpRequest->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));

        return ApiResponse::success($requests);
    }

    /**
     * 下载 GDPR 导出文件
     *
     * GET /api/gdpr/requests/{request}/download
     */
    public function download(GdprDataRequest $request): JsonResponse
    {
        if ($request->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            return ApiResponse::error('FORBIDDEN', '无权访问此文件', 403);
        }

        $filePath = $this->gdprService->downloadExport($request);
        if (! $filePath) {
            return ApiResponse::error('FILE_NOT_FOUND', '导出文件不存在或已过期', 404);
        }

        return response()->download($filePath);
    }

    // ─── 管理端 DSR 处理 ───

    /**
     * DSR 请求管理列表
     *
     * GET /api/gdpr/requests
     */
    public function index(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'status' => 'nullable|in:pending,processing,completed,approved,rejected,failed',
            'type' => 'nullable|in:access,export,rectification,erasure,restrict,portability,object',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = GdprDataRequest::with(['user', 'processor']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $requests->getCollection()->transform(fn($r) => $this->formatRequest($r));

        return ApiResponse::success($requests);
    }

    /**
     * DSR 请求详情
     *
     * GET /api/gdpr/requests/{request}
     */
    public function show(GdprDataRequest $request): JsonResponse
    {
        $request->load(['user', 'processor']);
        return ApiResponse::success($this->formatRequest($request));
    }

    /**
     * 处理 DSR 请求
     *
     * POST /api/gdpr/requests/{request}/process
     */
    public function process(GdprDataRequest $request): JsonResponse
    {
        if (! $request->isProcessable()) {
            return ApiResponse::error('INVALID_STATUS', '该请求当前状态不可处理', 400);
        }

        $request->update(['processed_by' => auth()->id()]);

        try {
            $result = match ($request->type) {
                GdprDataRequest::TYPE_ACCESS,
                GdprDataRequest::TYPE_EXPORT => $this->gdprService->processAccessRequest($request),
                GdprDataRequest::TYPE_PORTABILITY => $this->gdprService->processPortabilityRequest($request),
                GdprDataRequest::TYPE_ERASURE => $this->gdprService->processErasureRequest($request),
                default => throw new \RuntimeException("不支持自动处理: {$request->type}"),
            };

            if ($result->isFailed()) {
                return ApiResponse::error('PROCESS_FAILED', $result->admin_notes ?? '处理失败', 500);
            }

            return ApiResponse::success($this->formatRequest($result), '请求已处理完成');
        } catch (\Throwable $e) {
            return ApiResponse::error('PROCESS_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * 审核 DSR 请求（批准/拒绝）
     *
     * POST /api/gdpr/requests/{request}/review
     */
    public function review(Request $httpRequest, GdprDataRequest $request): JsonResponse
    {
        $validated = $httpRequest->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:1000',
        ]);

        $request->update([
            'status' => $validated['action'] === 'approve'
                ? GdprDataRequest::STATUS_APPROVED
                : GdprDataRequest::STATUS_REJECTED,
            'rejection_reason' => $validated['reason'] ?? null,
            'processed_by' => auth()->id(),
            'completed_at' => now(),
        ]);

        return ApiResponse::success(
            $this->formatRequest($request->fresh()),
            $validated['action'] === 'approve' ? '请求已批准' : '请求已拒绝'
        );
    }

    /**
     * GDPR 统计概览
     *
     * GET /api/gdpr/stats
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->gdprService->getStats());
    }

    // ─── DPA 管理 ───

    /**
     * DPA 列表
     *
     * GET /api/gdpr/dpa
     */
    public function dpaIndex(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'status' => 'nullable|in:draft,published,archived',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DataProcessingAgreement::with(['signatures']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        $dpas = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $dpas->getCollection()->transform(fn($d) => [
            'id' => $d->id,
            'tenant_id' => $d->tenant_id,
            'title' => $d->title,
            'version' => $d->version,
            'status' => $d->status,
            'jurisdiction' => $d->jurisdiction,
            'effective_at' => $d->effective_at?->toIso8601String(),
            'expires_at' => $d->expires_at?->toIso8601String(),
            'data_categories' => $d->data_categories,
            'sub_processors' => $d->sub_processors,
            'signatures_count' => $d->signatures->count(),
            'created_at' => $d->created_at->toIso8601String(),
        ]);

        return ApiResponse::success($dpas);
    }

    /**
     * 创建 DPA
     *
     * POST /api/gdpr/dpa
     */
    public function storeDpa(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:20',
            'content' => 'required|string',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'data_categories' => 'nullable|array',
            'data_categories.*' => 'string',
            'processing_purposes' => 'nullable|array',
            'processing_purposes.*' => 'string',
            'sub_processors' => 'nullable|array',
            'sub_processors.*.name' => 'required|string',
            'sub_processors.*.purpose' => 'required|string',
            'sub_processors.*.location' => 'required|string',
            'security_measures' => 'nullable|array',
            'security_measures.*' => 'string',
            'jurisdiction' => 'nullable|string|max:100',
            'expires_at' => 'nullable|date',
        ]);

        $dpa = DataProcessingAgreement::create(array_merge($validated, [
            'status' => DataProcessingAgreement::STATUS_DRAFT,
        ]));

        return ApiResponse::success($dpa, 'DPA 已创建');
    }

    /**
     * 更新 DPA
     *
     * PUT /api/gdpr/dpa/{dpa}
     */
    public function updateDpa(Request $httpRequest, DataProcessingAgreement $dpa): JsonResponse
    {
        if ($dpa->status === DataProcessingAgreement::STATUS_PUBLISHED) {
            return ApiResponse::error('CANNOT_UPDATE', '已发布的 DPA 不能编辑', 400);
        }

        $validated = $httpRequest->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'data_categories' => 'nullable|array',
            'data_categories.*' => 'string',
            'processing_purposes' => 'nullable|array',
            'processing_purposes.*' => 'string',
            'sub_processors' => 'nullable|array',
            'sub_processors.*.name' => 'required|string',
            'sub_processors.*.purpose' => 'required|string',
            'sub_processors.*.location' => 'required|string',
            'security_measures' => 'nullable|array',
            'security_measures.*' => 'string',
            'jurisdiction' => 'nullable|string|max:100',
        ]);

        $dpa->update($validated);

        return ApiResponse::success($dpa->fresh(), 'DPA 已更新');
    }

    /**
     * 发布 DPA
     *
     * POST /api/gdpr/dpa/{dpa}/publish
     */
    public function publishDpa(DataProcessingAgreement $dpa): JsonResponse
    {
        try {
            $result = $this->gdprService->publishDpa($dpa->id);
            return ApiResponse::success($result, 'DPA 已发布');
        } catch (\Throwable $e) {
            return ApiResponse::error('PUBLISH_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 签署 DPA
     *
     * POST /api/gdpr/dpa/{dpa}/sign
     */
    public function signDpa(Request $httpRequest, DataProcessingAgreement $dpa): JsonResponse
    {
        $tenantId = $httpRequest->user()->tenant_id;
        if (! $tenantId) {
            return ApiResponse::error('NO_TENANT', '当前用户无关联租户', 400);
        }

        try {
            $signature = $this->gdprService->signDpa($dpa->id, $tenantId, $httpRequest->user()->id);
            return ApiResponse::success($signature, 'DPA 已签署');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('SIGN_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 获取当前用户租户的 DPA 签署状态
     *
     * GET /api/gdpr/dpa/my-status
     */
    public function myDpaStatus(Request $httpRequest): JsonResponse
    {
        $tenantId = $httpRequest->user()->tenant_id;

        $publishedDpas = DataProcessingAgreement::where('status', DataProcessingAgreement::STATUS_PUBLISHED)
            ->get();

        $result = $publishedDpas->map(function ($dpa) use ($tenantId) {
            $signature = $tenantId ? DpaSignature::where('dpa_id', $dpa->id)
                ->where('tenant_id', $tenantId)
                ->first() : null;

            return [
                'id' => $dpa->id,
                'title' => $dpa->title,
                'version' => $dpa->version,
                'effective_at' => $dpa->effective_at?->toIso8601String(),
                'signed' => ! is_null($signature),
                'signed_at' => $signature?->signed_at?->toIso8601String(),
                'signer_name' => $signature?->signer_name,
            ];
        });

        return ApiResponse::success($result);
    }

    /**
     * 格式化请求数据
     */
    protected function formatRequest(GdprDataRequest $r): array
    {
        return [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'user_name' => $r->user?->name,
            'user_email' => $r->user?->email,
            'type' => $r->type,
            'type_label' => $r->getTypeLabel(),
            'status' => $r->status,
            'status_label' => $r->getStatusLabel(),
            'reason' => $r->reason,
            'request_data' => $r->request_data,
            'output_file' => $r->output_file ? Storage::disk(GdprComplianceService::EXPORT_DISK)->url($r->output_file) : null,
            'file_size' => $r->file_size,
            'expires_at' => $r->expires_at?->toIso8601String(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'processed_by' => $r->processed_by,
            'processor_name' => $r->processor?->name,
            'rejection_reason' => $r->rejection_reason,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }
}
