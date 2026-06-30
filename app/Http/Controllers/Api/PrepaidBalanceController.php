<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PrepaidBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 预付余额 & 信用额度管理（M3-56）
 */
class PrepaidBalanceController extends Controller
{
    public function __construct(
        protected PrepaidBalanceService $prepaidService,
    ) {}

    // ═══════════════════════════════════════════
    // 客户侧 API
    // ═══════════════════════════════════════════

    /**
     * 我的余额概览
     */
    public function myBalance(Request $request): JsonResponse
    {
        $customer = $this->getCustomer($request);
        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $funds = $this->prepaidService->getAvailableFunds($customer);
        $transactions = $this->prepaidService->getTransactions($customer,
            $request->only(['type', 'status', 'date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );

        $autoRecharge = $this->prepaidService->getAutoRechargeSettings($customer);

        return response()->json([
            'funds' => $funds,
            'recent_transactions' => $transactions,
            'auto_recharge' => $autoRecharge,
        ]);
    }

    /**
     * 充值
     */
    public function recharge(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'payment_method' => 'required|string|in:alipay,wechat,balance',
            'description' => 'nullable|string|max:200',
        ]);

        $customer = $this->getCustomer($request);
        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $result = $this->prepaidService->recharge(
            $customer,
            (float) $request->amount,
            $request->payment_method,
            'CNY',
            $request->description,
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'balance_after' => $result['balance_after'],
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * 交易记录
     */
    public function myTransactions(Request $request): JsonResponse
    {
        $customer = $this->getCustomer($request);
        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $transactions = $this->prepaidService->getTransactions($customer,
            $request->only(['type', 'status', 'date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );

        return response()->json($transactions);
    }

    /**
     * 保存自动充值设置
     */
    public function saveAutoRecharge(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'threshold' => 'required_if:enabled,true|numeric|min:0',
            'amount' => 'required_if:enabled,true|numeric|min:0.01',
            'payment_method' => 'required_if:enabled,true|string',
        ]);

        $customer = $this->getCustomer($request);
        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $balance = $this->prepaidService->saveAutoRechargeSettings(
            $customer,
            (bool) $request->enabled,
            (float) $request->threshold,
            (float) $request->amount,
            $request->payment_method ?? 'alipay',
        );

        return response()->json(['success' => true, 'auto_recharge' => $balance->metadata['auto_recharge'] ?? null]);
    }

    /**
     * 检查并触发自动充值
     */
    public function checkAutoRecharge(Request $request): JsonResponse
    {
        $customer = $this->getCustomer($request);
        if (! $customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $result = $this->prepaidService->checkAutoRecharge($customer);

        return response()->json($result ?? ['auto_recharged' => false, 'reason' => '无需自动充值']);
    }

    // ═══════════════════════════════════════════
    // 管理员 API
    // ═══════════════════════════════════════════

    /**
     * 管理员：查询客户余额
     */
    public function customerBalance(Request $request, Customer $customer): JsonResponse
    {
        $funds = $this->prepaidService->getAvailableFunds($customer);
        $balance = $this->prepaidService->getBalance($customer);

        return response()->json([
            'funds' => $funds,
            'balance' => $balance,
        ]);
    }

    /**
     * 管理员：手动充值
     */
    public function adminRecharge(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:200',
        ]);

        $result = $this->prepaidService->adminRecharge(
            $customer,
            (float) $request->amount,
            'CNY',
            $request->description,
            $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'transaction' => $result,
            'balance_after' => $customer->fresh()->prepaid_balance,
        ]);
    }

    /**
     * 管理员：手动扣款
     */
    public function adminDeduct(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:200',
        ]);

        $result = $this->prepaidService->adjust(
            $customer,
            -(float) $request->amount,
            'CNY',
            $request->description ?? "管理员手动扣款 {$request->amount}",
            $request->user()?->id,
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result);
    }

    /**
     * 管理员：调账
     */
    public function adminAdjust(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'nullable|string|max:200',
        ]);

        $result = $this->prepaidService->adjust(
            $customer,
            (float) $request->amount,
            'CNY',
            $request->description,
            $request->user()?->id,
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result);
    }

    /**
     * 管理员：交易记录（指定客户）
     */
    public function adminTransactions(Request $request, Customer $customer): JsonResponse
    {
        $transactions = $this->prepaidService->getTransactions($customer,
            $request->only(['type', 'status', 'date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );

        return response()->json($transactions);
    }

    /**
     * 管理员：设置信用额度
     */
    public function setCreditLimit(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'credit_limit' => 'required|numeric|min:0|max:9999999.99',
            'grace_days' => 'nullable|integer|min:0|max:365',
        ]);

        $credit = $this->prepaidService->setCreditLimit(
            $customer,
            (float) $request->credit_limit,
            (int) ($request->grace_days ?? 0),
        );

        return response()->json([
            'success' => true,
            'credit_limit' => $credit,
        ]);
    }

    /**
     * 管理员：查询信用额度
     */
    public function getCreditLimit(Request $request, Customer $customer): JsonResponse
    {
        $credit = $this->prepaidService->getCreditLimit($customer);

        return response()->json([
            'credit_limit' => $credit,
            'available_credit' => $credit->available_credit,
        ]);
    }

    /**
     * 管理员：余额统计概览
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;

        $stats = $this->prepaidService->getStats($tenantId);

        return response()->json($stats);
    }

    /**
     * 管理员：全部交易记录
     */
    public function allTransactions(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;

        $query = \App\Models\PrepaidTransaction::where('tenant_id', $tenantId);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json($transactions);
    }

    // ═══════════════════════════════════════════
    // 辅助方法
    // ═══════════════════════════════════════════

    protected function getCustomer(Request $request): ?Customer
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        return Customer::where('user_id', $user->id)->first();
    }
}
