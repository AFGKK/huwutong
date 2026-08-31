<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 客户支付方式管理（Portal 端）
 *
 * 允许客户管理自己的支付方式，支持多卡管理、默认支付方式设置。
 */
class PaymentMethodController extends Controller
{
    public function __construct()
    {
    }

    /**
     * 获取当前客户的支付方式列表
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['success' => false, 'message' => __('app.api.payment_method.customer_not_found')], 404);
        }

        $methods = PaymentMethod::where('customer_id', $customer->id)
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }

    /**
     * 添加支付方式
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['success' => false, 'message' => __('app.api.payment_method.customer_not_found')], 404);
        }

        $validator = Validator::make($request->all(), [
            'gateway' => 'required|string|max:30',
            'method_type' => 'required|string|max:30',
            'gateway_method_id' => 'required|string|max:255',
            'last_four' => 'nullable|string|size:4',
            'card_brand' => 'nullable|string|max:30',
            'cardholder_name' => 'nullable|string|max:255',
            'expiry_month' => 'nullable|integer|between:1,12',
            'expiry_year' => 'nullable|integer|min:2024',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|size:2',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $customer->tenant_id;
        $data['customer_id'] = $customer->id;

        // 如果是第一个支付方式，自动设为默认
        $existingCount = PaymentMethod::where('customer_id', $customer->id)->count();
        if ($existingCount === 0) {
            $data['is_default'] = true;
        }

        $method = PaymentMethod::create($data);

        if (!empty($data['is_default'])) {
            $method->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => __('app.api.payment_method.added'),
            'data' => $method->fresh(),
        ], 201);
    }

    /**
     * 设为默认支付方式
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer || $paymentMethod->customer_id !== $customer->id) {
            return response()->json(['success' => false, 'message' => __('app.api.payment_method.forbidden')], 403);
        }

        $paymentMethod->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => __('app.api.payment_method.set_default'),
            'data' => $paymentMethod->fresh(),
        ]);
    }

    /**
     * 删除支付方式
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer || $paymentMethod->customer_id !== $customer->id) {
            return response()->json(['success' => false, 'message' => __('app.api.payment_method.forbidden')], 403);
        }

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->update(['is_active' => false]);

        // 如果删除的是默认支付方式，指定另一个为默认
        if ($wasDefault) {
            $nextDefault = PaymentMethod::where('customer_id', $customer->id)
                ->where('id', '!=', $paymentMethod->id)
                ->where('is_active', true)
                ->first();

            if ($nextDefault) {
                $nextDefault->setAsDefault();
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('app.api.payment_method.deleted'),
        ]);
    }

    // ═══════════════════════════════════════
    //  管理端 API
    // ═══════════════════════════════════════

    /**
     * 管理端仪表盘统计
     *
     * GET /api/admin/payment-methods/dashboard
     */
    public function adminDashboard(): JsonResponse
    {
        $total = PaymentMethod::count();
        $active = PaymentMethod::active()->count();
        $expiringSoon = PaymentMethod::active()
            ->where('expiry_year', '<=', now()->addDays(30)->year)
            ->count();

        $brandStats = PaymentMethod::active()
            ->select('card_brand', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('card_brand')
            ->pluck('count', 'card_brand')
            ->toArray();

        return response()->json([
            'total_methods' => $total,
            'active_methods' => $active,
            'expiring_soon' => $expiringSoon,
            'brand_distribution' => $brandStats,
            'avg_per_customer' => $active > 0 ? round($total / max(PaymentMethod::distinct('customer_id')->count('customer_id'), 1), 1) : 0,
        ]);
    }

    /**
     * 管理端列表
     *
     * GET /api/admin/payment-methods
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = $request->input('search');

        $query = PaymentMethod::with('customer')->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('last_four', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('cardholder_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * 管理端查看详情
     *
     * GET /api/admin/payment-methods/{paymentMethod}
     */
    public function adminShow(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->load('customer');
        return response()->json(['data' => $paymentMethod]);
    }

    /**
     * 管理端软删除
     *
     * DELETE /api/admin/payment-methods/{paymentMethod}
     */
    public function adminDestroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => __('app.api.payment_method.disabled')]);
    }

    /**
     * 管理端强制删除
     *
     * DELETE /api/admin/payment-methods/{paymentMethod}/force
     */
    public function adminForceDelete(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->forceDelete();

        return response()->json(['success' => true, 'message' => __('app.api.payment_method.permanently_deleted')]);
    }
}
