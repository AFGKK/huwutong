<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 后台页面数据接口
 */
class AdminPageDataController extends Controller
{
    // ─── 产品使用分析 ───
    public function productAnalyticsSummary(): JsonResponse
    {
        $totalProducts = Product::count();
        $totalLicenses = License::count();
        $activeLicenses = License::where('status', 'active')->count();
        $newLicensesPeriod = License::where('created_at', '>=', now()->subDays(30))->count();
        $totalDevices = DB::table('devices')->count();

        return ApiResponse::success([
            'total_products' => $totalProducts,
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeLicenses,
            'new_licenses_period' => $newLicensesPeriod,
            'activation_rate' => $totalLicenses > 0 ? round($activeLicenses / $totalLicenses * 100, 1) : 0,
            'total_devices' => $totalDevices,
            'period_days' => 30,
        ]);
    }
    public function productAnalyticsRanking(): JsonResponse { return ApiResponse::success([]); }
    public function productAnalyticsTrend(): JsonResponse { return ApiResponse::success([]); }
    public function productAnalyticsActivationTrend(): JsonResponse { return ApiResponse::success([]); }
    public function productAnalyticsMonthlyTrend(): JsonResponse { return ApiResponse::success([]); }
    public function productAnalyticsRegional(): JsonResponse { return ApiResponse::success([]); }
    public function productAnalyticsRegionalTrend(): JsonResponse { return ApiResponse::success([]); }
    public function productAnalyticsModuleUsage(): JsonResponse { return ApiResponse::success([]); }

    // ─── 电商数据分析 ───
    public function ecommerceSummary(): JsonResponse
    {
        $totalRevenue = Order::where('status', 'paid')->sum('total_amount');
        $totalOrders = Order::count();
        $totalCustomers = User::count();
        $newCustomers = User::where('created_at', '>=', now()->subDays(30))->count();
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
        $lastMonthRevenue = Order::where('status', 'paid')
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->sum('total_amount');
        $revenueGrowth = $lastMonthRevenue > 0 ? round(($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100, 1) : 0;
        $prevOrders = Order::where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))->count();
        $orderGrowth = $prevOrders > 0 ? round(($totalOrders - $prevOrders) / $prevOrders * 100, 1) : 0;

        return ApiResponse::success([
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'total_customers' => $totalCustomers,
            'new_customers' => $newCustomers,
            'avg_order_value' => $avgOrderValue,
            'revenue_growth' => $revenueGrowth,
            'order_growth' => $orderGrowth,
        ]);
    }
    public function ecommerceComparison(): JsonResponse
    {
        return ApiResponse::success(['current' => ['revenue' => 0, 'orders' => 0, 'avg_order' => 0]]);
    }
    public function ecommerceSalesTrend(): JsonResponse { return ApiResponse::success([]); }
    public function ecommerceProductRanking(): JsonResponse { return ApiResponse::success([]); }
    public function ecommerceRepurchaseRate(): JsonResponse { return ApiResponse::success([]); }
    public function ecommercePaymentChannels(): JsonResponse { return ApiResponse::success([]); }
    public function ecommerceForecast(): JsonResponse { return ApiResponse::success([]); }

    // ─── 组合套餐 ───
    public function bundlesList(): JsonResponse { return ApiResponse::success([]); }
    public function bundlesStats(): JsonResponse { return ApiResponse::success(['total_bundles' => 0, 'active_bundles' => 0, 'total_purchases' => 0, 'total_revenue' => 0]); }
    public function bundlesDetail($id): JsonResponse { return ApiResponse::success(null); }
    public function bundlesPurchases(): JsonResponse { return ApiResponse::success([]); }

    // ─── 预售/众筹 ───
    public function preSaleStats(): JsonResponse { return ApiResponse::success(['total' => 0, 'active' => 0, 'success' => 0, 'failed' => 0, 'totalRaised' => 0, 'totalBackers' => 0]); }
    public function preSaleList(): JsonResponse { return ApiResponse::success([]); }
    public function preSaleDetail($id): JsonResponse { return ApiResponse::success(null); }

    // ─── 秒杀/抢购 ───
    public function flashSaleDashboard(): JsonResponse {
        $now = now();
        return ApiResponse::success([
            'total' => \App\Models\FlashSale::count(),
            'active' => \App\Models\FlashSale::where('status', 'active')->where('start_time', '<=', $now)->where('end_time', '>=', $now)->count(),
            'scheduled' => \App\Models\FlashSale::where('status', 'active')->where('start_time', '>', $now)->count(),
            'totalOrders' => \App\Models\FlashSaleOrder::count(),
            'paidOrders' => \App\Models\FlashSaleOrder::whereNotNull('paid_at')->count(),
            'today_sales' => \App\Models\FlashSaleOrder::whereDate('created_at', today())->count(),
            'today_revenue' => 0,
        ]);
    }
    public function flashSaleList(Request $request): JsonResponse {
        $query = \App\Models\FlashSale::with('sku.product:id,name');
        $sales = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));
        return ApiResponse::success($sales);
    }
    public function flashSaleDetail($id): JsonResponse {
        $sale = \App\Models\FlashSale::with('sku.product')->find($id);
        return ApiResponse::success($sale);
    }

    // ─── 二级市场转售 ───
    public function resaleDashboard(): JsonResponse { return ApiResponse::success(['total_listings' => 0, 'active_listings' => 0, 'total_sold' => 0, 'total_volume' => 0]); }
    public function resaleList(): JsonResponse { return ApiResponse::success([]); }
    public function resaleDetail($id): JsonResponse { return ApiResponse::success(null); }

    // ─── 规格对比 ───
    public function productComparisonList(): JsonResponse { return ApiResponse::success([]); }
    public function productComparisonDetail($id): JsonResponse { return ApiResponse::success(null); }

    // ─── 促销引擎 ───
    public function promotionEngineList(): JsonResponse { return ApiResponse::success([]); }
    public function promotionEngineStats(): JsonResponse { return ApiResponse::success(['total' => 0, 'active' => 0]); }

    // ─── 续费提醒 ───
    public function renewalReminderDashboard(): JsonResponse { return ApiResponse::success(['total_due' => 0, 'reminded' => 0, 'renewed' => 0, 'revenue_recovered' => 0]); }
    public function renewalReminderList(): JsonResponse { return ApiResponse::success([]); }

    // ─── License 继承/合并 ───
    public function licenseMergeList(): JsonResponse { return ApiResponse::success([]); }
    public function licenseMergeStats(): JsonResponse { return ApiResponse::success(['total_merges' => 0, 'pending' => 0, 'completed' => 0]); }

    // ─── License 二级市场 ───
    public function licenseMarketplaceDashboard(): JsonResponse { return ApiResponse::success(['total_listings' => 0, 'active' => 0, 'sold' => 0, 'total_volume' => 0]); }
    public function licenseMarketplaceListings(): JsonResponse { return ApiResponse::success([]); }
    public function licenseMarketplaceTransactions(): JsonResponse { return ApiResponse::success([]); }
    public function licenseMarketplaceDisputes(): JsonResponse { return ApiResponse::success([]); }

    // ─── 开发者认证 ───
    public function certificationList(): JsonResponse { return ApiResponse::success([]); }
    public function certificationStats(): JsonResponse { return ApiResponse::success(['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0]); }

    // ─── 转化漏斗 ───
    public function funnelDashboard(): JsonResponse
    {
        return ApiResponse::success([
            'funnel' => ['stages' => [], 'total_started' => 0, 'total_converted' => 0, 'overall_rate' => 0],
            'today_registered' => 0, 'worst_stage' => null,
        ]);
    }
    public function funnelData(): JsonResponse { return ApiResponse::success(['stages' => []]); }
    public function funnelBySource(): JsonResponse { return ApiResponse::success([]); }
    public function funnelTrend(): JsonResponse { return ApiResponse::success([]); }

    // ─── 邮件营销 ───
    public function emailDripDashboard(): JsonResponse
    {
        return ApiResponse::success([
            'total_campaigns' => 0, 'active_campaigns' => 0, 'total_sent' => 0, 'open_rate' => 0, 'click_rate' => 0,
        ]);
    }
    public function emailDripCampaigns(): JsonResponse { return ApiResponse::success([]); }
    public function emailDripCampaignDetail($id): JsonResponse { return ApiResponse::success(null); }
    public function emailDripTriggers(): JsonResponse { return ApiResponse::success([]); }

    /**
     * 占位路由已停用：真实 API 由 catalog/platform/enterprise/extensions 等模块注册。
     * 此前在此重复注册会覆盖真实控制器并返回空数据。
     */
    public static function registerRoutes(): void
    {
        // intentionally empty
    }
}
