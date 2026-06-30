<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceTitle;
use App\Models\Order;
use App\Services\AutoInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 自动开票系统 (M2-148 🛒)
 */
class AutoInvoiceController extends Controller
{
    public function __construct(
        protected AutoInvoiceService $autoInvoiceService,
    ) {}

    /**
     * 从订单生成发票
     */
    public function generate(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('items')
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($orderId);

        if ($order->status !== 'paid') {
            return ApiResponse::error('ORDER_NOT_PAID', '订单未支付，无法开票', 400);
        }

        if ($order->invoice_id) {
            return ApiResponse::error('INVOICE_EXISTS', '该订单已开票', 400);
        }

        $validated = $request->validate([
            'invoice_title_id' => 'nullable|exists:invoice_titles,id',
        ]);

        try {
            $invoice = $this->autoInvoiceService->generateFromOrder(
                $order,
                $validated['invoice_title_id'] ?? null
            );
            return ApiResponse::success($invoice->load('lineItems'), '发票生成成功');
        } catch (\Throwable $e) {
            return ApiResponse::error('INVOICE_GENERATE_FAILED', '发票生成失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 发票列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::paginated(
            $this->autoInvoiceService->getTenantInvoices($tenantId, $request->all())
        );
    }

    /**
     * 发票详情
     */
    public function show(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::with([
            'lineItems', 'customer',
            'customer.user:id,name,email',
        ])->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($invoiceId);

        return ApiResponse::success($invoice);
    }

    /**
     * 发票 HTML 预览
     */
    public function preview(Request $request, int $invoiceId): \Illuminate\Http\Response
    {
        $invoice = Invoice::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($invoiceId);

        $html = $this->autoInvoiceService->getInvoiceHtml($invoice->id);
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 重发发票邮件
     */
    public function resend(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($invoiceId);

        try {
            $this->autoInvoiceService->resendInvoiceEmail($invoice->id);
            return ApiResponse::success(null, '发票邮件已重新发送');
        } catch (\Throwable $e) {
            return ApiResponse::error('RESEND_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 发票统计
     */
    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->autoInvoiceService->getStats($request->user()->tenant_id)
        );
    }

    // ═══════════════ 发票抬头管理 ═══════════════

    /**
     * 发票抬头列表
     */
    public function titles(Request $request): JsonResponse
    {
        $customerId = $this->resolveCustomerId($request->user());

        $titles = InvoiceTitle::where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
        return ApiResponse::success($titles);
    }

    /**
     * 解析客户 ID（支持超级管理员无 customer_id 的情况）
     */
    protected function resolveCustomerId($user): int
    {
        if ($user->customer_id) {
            return $user->customer_id;
        }
        // 超级管理员等可能没有直接关联 customer_id，查找对应客户
        $customer = \App\Models\Customer::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->first();
        return $customer?->id ?? 0;
    }

    /**
     * 创建发票抬头
     */
    public function storeTitle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'tax_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:300',
            'phone' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:200',
            'bank_account' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ]);

        $user = $request->user();
        $customerId = $this->resolveCustomerId($user);

        if (!$customerId) {
            return ApiResponse::error('MISSING_CUSTOMER', '未找到客户信息，请先完善账户资料', 400);
        }

        $data['customer_id'] = $customerId;
        $data['tenant_id'] = $user->tenant_id;

        if (!empty($data['is_default'])) {
            InvoiceTitle::where('customer_id', $customerId)
                ->update(['is_default' => false]);
        }

        $title = InvoiceTitle::create($data);
        return ApiResponse::success($title, '发票抬头已创建');
    }

    /**
     * 更新发票抬头
     */
    public function updateTitle(Request $request, int $titleId): JsonResponse
    {
        $title = InvoiceTitle::where('customer_id', $request->user()->customer_id)
            ->findOrFail($titleId);

        $data = $request->validate([
            'title' => 'string|max:200',
            'tax_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:300',
            'phone' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:200',
            'bank_account' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ]);

        if (!empty($data['is_default'])) {
            InvoiceTitle::where('customer_id', $request->user()->customer_id)
                ->where('id', '!=', $titleId)
                ->update(['is_default' => false]);
        }

        $title->update($data);
        return ApiResponse::success($title->fresh(), '发票抬头已更新');
    }

    /**
     * 删除发票抬头
     */
    public function destroyTitle(Request $request, int $titleId): JsonResponse
    {
        $title = InvoiceTitle::where('customer_id', $request->user()->customer_id)
            ->findOrFail($titleId);
        $title->delete();
        return ApiResponse::success(null, '发票抬头已删除');
    }
}
