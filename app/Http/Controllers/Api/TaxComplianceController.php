<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TaxComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaxComplianceController extends Controller
{
    public function __construct(
        protected TaxComplianceService $service
    ) {}

    /**
     * 合规仪表盘
     */
    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    // ─── 报告管理 ───

    public function reports(Request $request)
    {
        return ApiResponse::success(
            $this->service->listReports(
                $request->user()->tenant_id,
                $request->only(['country', 'status', 'report_type', 'period', 'per_page'])
            )
        );
    }

    public function generateReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string|size:2',
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'report_type' => 'nullable|string|in:vat_return,gst_return,sales_tax,cross_border,liability_summary',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $report = $this->service->generateReport(
            $request->user()->tenant_id,
            $request->country,
            $request->period,
            $request->report_type ?? 'vat_return',
        );

        return ApiResponse::success($report);
    }

    public function fileReport(Request $request, int $reportId)
    {
        return ApiResponse::success(
            $this->service->fileReport($request->user()->tenant_id, $reportId)
        );
    }

    // ─── 文档管理 ───

    public function documents(Request $request)
    {
        return ApiResponse::success(
            $this->service->listDocuments(
                $request->user()->tenant_id,
                $request->only(['country', 'status', 'document_type', 'search', 'per_page'])
            )
        );
    }

    public function storeDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:tax_return,filing_receipt,correspondence,certificate,audit_letter',
            'country' => 'required|string|size:2',
            'title' => 'required|string|max:200',
            'reference_number' => 'nullable|string|max:100',
            'document_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:document_date',
            'status' => 'nullable|string|in:pending,completed,overdue,archived',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->createDocument(
                $request->user()->tenant_id,
                $request->user()->id,
                $request->only(['document_type', 'country', 'title', 'reference_number', 'document_date', 'due_date', 'status', 'notes'])
            )
        );
    }

    public function updateDocument(Request $request, int $documentId)
    {
        return ApiResponse::success(
            $this->service->updateDocument(
                $request->user()->tenant_id,
                $documentId,
                $request->only(['status', 'title', 'reference_number', 'notes', 'due_date'])
            )
        );
    }

    public function destroyDocument(Request $request, int $documentId)
    {
        $this->service->deleteDocument($request->user()->tenant_id, $documentId);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 规则管理 ───

    public function rules(Request $request)
    {
        return ApiResponse::success(
            $this->service->listRules(
                $request->user()->tenant_id,
                $request->only(['rule_type', 'country', 'is_active', 'per_page'])
            )
        );
    }

    public function storeRule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'rule_type' => 'required|string|in:reduced_rate,exemption,threshold,special_zone',
            'country' => 'nullable|string|size:2',
            'region_code' => 'nullable|string|max:10',
            'condition_type' => 'nullable|string|max:40',
            'condition_value' => 'nullable|string|max:100',
            'rate_modifier' => 'nullable|numeric|min:0|max:1',
            'action' => 'required|string|in:apply_rate,exempt,reduce_rate,reverse_charge',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->createRule($request->user()->tenant_id, $request->all())
        );
    }

    public function updateRule(Request $request, int $ruleId)
    {
        return ApiResponse::success(
            $this->service->updateRule($request->user()->tenant_id, $ruleId, $request->all())
        );
    }

    public function destroyRule(Request $request, int $ruleId)
    {
        $this->service->deleteRule($request->user()->tenant_id, $ruleId);
        return ApiResponse::success(['deleted' => true]);
    }
}
