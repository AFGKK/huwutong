<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商品搜索管理 (M2-156 🛒)
 */
class ProductSearchAdminController extends Controller
{
    public function __construct(
        protected ProductSearchService $productSearch,
    ) {}

    /**
     * 搜索统计
     *
     * GET /api/admin/product-search/stats
     */
    public function stats(): JsonResponse
    {
        $total = \App\Models\ProductSearchLog::count();
        $today = \App\Models\ProductSearchLog::whereDate('created_at', today())->count();
        $uniqueTerms = \App\Models\ProductSearchLog::distinct('term')->count('term');
        $zeroResult = \App\Models\ProductSearchLog::where('result_count', 0)->count();
        $totalQueries = max($total, 1);

        return response()->json([
            'total_searches' => $total,
            'today_searches' => $today,
            'unique_terms' => $uniqueTerms,
            'zero_result_rate' => round(($zeroResult / $totalQueries) * 100, 1) . '%',
        ]);
    }

    /**
     * 热门搜索词
     *
     * GET /api/admin/product-search/hot-terms
     */
    public function hotTerms(): JsonResponse
    {
        $terms = \App\Models\ProductSearchLog::select('term', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('term')
            ->orderByDesc('count')
            ->limit(config('product-search.logging.hot_terms_limit', 20))
            ->get();

        return response()->json(['data' => $terms]);
    }

    /**
     * 无结果搜索词
     *
     * GET /api/admin/product-search/zero-result-terms
     */
    public function zeroResultTerms(): JsonResponse
    {
        $terms = \App\Models\ProductSearchLog::select('term', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->where('result_count', 0)
            ->groupBy('term')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        return response()->json(['data' => $terms]);
    }

    /**
     * 搜索配置
     *
     * GET /api/admin/product-search/config
     */
    public function config(): JsonResponse
    {
        return response()->json(config('product-search'));
    }

    /**
     * 搜索日志
     *
     * GET /api/admin/product-search/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = \App\Models\ProductSearchLog::orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where('term', 'like', "%{$search}%");
        }

        return response()->json($query->paginate($perPage));
    }
}
