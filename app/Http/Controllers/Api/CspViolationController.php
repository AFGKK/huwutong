<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CspViolation;
use App\Services\CspManagerService;
use Illuminate\Http\Request;

class CspViolationController extends Controller
{
    public function __construct(
        protected CspManagerService $cspManager,
    ) {}

    /**
     * 接收 CSP 违规报告（report-to endpoint）
     * 浏览器 POST JSON 到 /api/csp-violations/report
     */
    public function report(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->cspManager->reportViolation($request);

        // 按 CSP 规范只需返回空 202 或 204
        return response()->json(['status' => 'ok'], 202);
    }

    /**
     * 获取违规列表（管理用）
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $violations = CspViolation::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::paginated($violations);
    }

    /**
     * 获取单个违规详情
     */
    public function show(CspViolation $cspViolation): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($cspViolation);
    }

    /**
     * 统计概览
     */
    public function stats(): \Illuminate\Http\JsonResponse
    {
        $total = CspViolation::count();
        $last24h = CspViolation::where('created_at', '>=', now()->subDay())->count();
        $topBlocked = CspViolation::selectRaw('blocked_uri, count(*) as cnt')
            ->whereNotNull('blocked_uri')
            ->groupBy('blocked_uri')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'total' => $total,
            'last_24h' => $last24h,
            'top_blocked_uris' => $topBlocked,
        ]);
    }
}
