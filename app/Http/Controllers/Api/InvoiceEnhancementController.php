<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InvoiceReconciliation;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceEnhancementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceEnhancementController extends Controller
{
    public function __construct(
        protected InvoiceEnhancementService $service
    ) {}

    // ─── 发票模板 ───

    public function templates(Request $request)
    {
        return ApiResponse::success(
            $this->service->listTemplates($request->user()->tenant_id, $request->only(['is_active']))
        );
    }

    public function storeTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:80|unique:invoice_templates,code',
            'is_default' => 'nullable|boolean',
            'header' => 'nullable|array',
            'footer' => 'nullable|array',
            'color_scheme' => 'nullable|string|max:10',
            'locale' => 'nullable|string|max:10',
            'currency' => 'nullable|string|size:3',
            'line_item_fields' => 'nullable|array',
            'show_fields' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createTemplate($data), 201);
    }

    public function updateTemplate(Request $request, InvoiceTemplate $invoiceTemplate)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'is_default' => 'nullable|boolean',
            'header' => 'nullable|array',
            'footer' => 'nullable|array',
            'color_scheme' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->service->updateTemplate($invoiceTemplate, $request->all()));
    }

    public function destroyTemplate(InvoiceTemplate $invoiceTemplate)
    {
        $this->service->deleteTemplate($invoiceTemplate);
        return ApiResponse::success(['deleted' => true]);
    }

    public function defaultTemplate(Request $request)
    {
        return ApiResponse::success($this->service->getDefaultTemplate($request->user()->tenant_id));
    }

    // ─── 账单对账 ───

    public function reconciliations(Request $request)
    {
        return ApiResponse::success(
            $this->service->listReconciliations(
                $request->user()->tenant_id,
                $request->only(['status', 'reconciliation_type', 'customer_id']),
                $request->input('per_page', 20)
            )
        );
    }

    public function storeReconciliation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'reconciliation_type' => 'nullable|string|in:auto,manual,import',
            'invoice_amount' => 'required|numeric|min:0',
            'actual_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_ref' => 'nullable|string|max:200',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createReconciliation($data), 201);
    }

    public function resolveReconciliation(Request $request, InvoiceReconciliation $invoiceReconciliation)
    {
        return ApiResponse::success(
            $this->service->resolveReconciliation(
                $invoiceReconciliation->id,
                $request->input('resolution', 'manual'),
                $request->input('notes')
            )
        );
    }

    public function reconciliationStats(Request $request)
    {
        return ApiResponse::success(
            $this->service->getReconciliationStats($request->user()->tenant_id)
        );
    }

    public function autoReconcile(Request $request)
    {
        return ApiResponse::success(
            $this->service->autoReconcile($request->user()->tenant_id)
        );
    }

    // ─── 账单拆分 ───

    public function splits(Request $request)
    {
        return ApiResponse::success(
            $this->service->listSplits(
                $request->user()->tenant_id,
                $request->only(['status']),
                $request->input('per_page', 20)
            )
        );
    }

    public function split(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'original_invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->service->splitInvoice(
                $request->user()->tenant_id,
                $request->input('original_invoice_id'),
                (float) $request->input('amount'),
                $request->input('reason')
            );
            return ApiResponse::success($result, 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['error' => $e->getMessage()], 400);
        }
    }

    // ─── 发票统计增强 ───

    public function enhancedStats(Request $request)
    {
        return ApiResponse::success(
            $this->service->getEnhancedStats($request->user()->tenant_id)
        );
    }
}
