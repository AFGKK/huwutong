<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Services\BillingHistoryPortalService;
use App\Services\BillingService;
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
        protected BillingService $subscriptionBilling,
    ) {}

    /**
     * 前台自助开通定价套餐
     *
     * POST /api/portal/billing/self-subscribe
     */
    public function selfSubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'nullable|integer|exists:pricing_plans,id',
            'plan_slug' => 'nullable|string|exists:pricing_plans,slug',
            'billing_period' => 'sometimes|in:monthly,quarterly,semi_annually,yearly',
            'auto_renew' => 'sometimes|boolean',
            'force_payment' => 'sometimes|boolean',
            'contact' => 'nullable|array',
            'contact.email' => 'nullable|email|max:255',
            'contact.phone' => 'nullable|string|max:20',
            'contact.name' => 'nullable|string|max:100',
        ]);

        if (empty($validated['plan_id']) && empty($validated['plan_slug'])) {
            return ApiResponse::error('VALIDATION_ERROR', 'plan_id 或 plan_slug 必填', 422);
        }

        $plan = ! empty($validated['plan_id'])
            ? PricingPlan::findOrFail($validated['plan_id'])
            : PricingPlan::where('slug', $validated['plan_slug'])->firstOrFail();

        try {
            $result = $this->subscriptionBilling->selfServeSubscribe(
                $request->user(),
                $plan,
                $validated['billing_period'] ?? 'monthly',
                [
                    'auto_renew' => $validated['auto_renew'] ?? true,
                    'force_payment' => $validated['force_payment'] ?? true,
                    'contact' => $validated['contact'] ?? null,
                ],
            );

            return ApiResponse::success([
                'requires_payment' => $result['requires_payment'],
                'status' => $result['status'],
                'already_active' => $result['already_active'] ?? false,
                'amount' => $result['amount'],
                'subscription' => $result['subscription'],
                'invoice' => $result['invoice'],
            ], $result['requires_payment']
                ? '订阅已创建，请完成支付以开通'
                : (($result['already_active'] ?? false) ? '套餐已开通' : '套餐已开通'));
        } catch (\Throwable $e) {
            return ApiResponse::error('SUBSCRIBE_FAILED', $e->getMessage(), 422);
        }
    }

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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        // 通过 customer 获取 tenant
        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
        }

        $invoice = $this->billingService->getInvoiceDetail($tenant, $customer, $id);

        if (! $invoice) {
            return ApiResponse::notFound(__('app.api.billing_portal.invoice_missing'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound(__('app.api.billing_portal.tenant_missing'));
        }

        $invoice = $this->billingService->getInvoiceDetail($tenant, $customer, $id);
        if (! $invoice) {
            return ApiResponse::notFound(__('app.api.billing_portal.invoice_missing'));
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

            return ApiResponse::success($result, $result['status'] === 'paid' ? __('app.api.billing_portal.pay_ok') : __('app.api.billing_portal.pay_initiated'));
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
            return ApiResponse::notFound(__('app.api.billing_portal.customer_missing'));
        }

        $invoice = Invoice::where('customer_id', $customer->id)->find($id);
        if (! $invoice) {
            return ApiResponse::notFound(__('app.api.billing_portal.invoice_missing'));
        }

        return ApiResponse::success(
            $this->paymentService->getPaymentStatus($customer, $invoice)
        );
    }
}
