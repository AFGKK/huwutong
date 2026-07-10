<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\BillingHistoryPortalService;
use App\Services\PortalInvoicePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-131 客户侧发票/账单历史完整查询 API
 *
 * 客户门户的账单历史模块：
 * - 完整账单列表 + 按时间段/状态筛选
 * - 账单详情（含订阅/税务信息）
 * - 统计概览
 * - 支付失败记录
 * - 自动续费扣款记录
 */
class BillingHistoryPortalController extends Controller
{
    public function __construct(
        protected BillingHistoryPortalService $billingService,
        protected PortalInvoicePaymentService $paymentService,
    ) {}

    /**
     * 获取账单列表
     *
     * GET /api/billing/invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        // 通过 customer 获取 tenant
        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $filters = $request->only([
            'status', 'date_from', 'date_to',
            'subscription_id', 'payment_method', 'billing_reason', 'sort',
        ]);

        $result = $this->billingService->getInvoices(
            tenant: $tenant,
            customer: $customer,
            filters: $filters,
            perPage: (int) $request->input('per_page', 20),
        );

        return ApiResponse::paginated($result);
    }

    /**
     * 获取账单详情
     *
     * GET /api/billing/invoices/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $invoice = $this->billingService->getInvoiceDetail($tenant, $customer, $id);

        if (! $invoice) {
            return ApiResponse::notFound('账单不存在');
        }

        return ApiResponse::success($invoice);
    }

    /**
     * 获取账单统计概览
     *
     * GET /api/billing/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $stats = $this->billingService->getStats($tenant, $customer);

        return ApiResponse::success($stats);
    }

    /**
     * 获取客户的订阅列表（用于筛选下拉）
     *
     * GET /api/billing/subscriptions
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $subscriptions = $this->billingService->getSubscriptions($tenant, $customer);

        return ApiResponse::success($subscriptions);
    }

    /**
     * 获取支付失败记录
     *
     * GET /api/billing/failed-payments
     */
    public function failedPayments(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $result = $this->billingService->getFailedPayments($tenant, $customer);

        return ApiResponse::success($result);
    }

    /**
     * 获取自动续费扣款记录
     *
     * GET /api/billing/auto-renewals
     */
    public function autoRenewals(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $result = $this->billingService->getAutoRenewalRecords($tenant, $customer);

        return ApiResponse::success($result);
    }

    /**
     * 获取筛选选项
     *
     * GET /api/billing/filter-options
     */
    public function filterOptions(): JsonResponse
    {
        return ApiResponse::success(
            $this->billingService->getFilterOptions()
        );
    }

    /**
     * 发起发票支付（异步 Webhook 确认）
     *
     * POST /api/billing/invoices/{id}/pay
     */
    public function payInvoice(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        $invoice = $this->billingService->getInvoiceDetail($tenant, $customer, $id);
        if (! $invoice) {
            return ApiResponse::notFound('账单不存在');
        }

        $validated = $request->validate([
            'payment_method' => 'nullable|string|in:gateway,prepaid,alipay,stripe,mock',
        ]);

        try {
            $result = $this->paymentService->payInvoice(
                $customer,
                Invoice::findOrFail($id),
                $validated['payment_method'] ?? null,
            );

            return ApiResponse::success($result, $result['status'] === 'paid' ? '支付成功' : '支付已发起');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PAYMENT_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 查询发票支付状态
     *
     * GET /api/billing/invoices/{id}/payment-status
     */
    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;
        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $invoice = Invoice::where('customer_id', $customer->id)->find($id);
        if (! $invoice) {
            return ApiResponse::notFound('账单不存在');
        }

        return ApiResponse::success(
            $this->paymentService->getPaymentStatus($customer, $invoice)
        );
    }
}
