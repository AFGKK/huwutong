<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TracingService;
use Illuminate\Http\Request;

class TracingController extends Controller
{
    public function __construct(
        protected TracingService $tracingService,
    ) {}

    /**
     * 调用链列表
     */
    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->tracingService->getTraces(
                $request->user()->tenant_id,
                $request->only(['method', 'path', 'is_slow', 'status_code', 'status_range', 'duration_min', 'duration_max', 'from', 'to', 'sort']),
                min((int) $request->get('per_page', 20), 100),
            )
        );
    }

    /**
     * 调用链统计
     */
    public function stats(Request $request)
    {
        return ApiResponse::success(
            $this->tracingService->getTraceStats(
                $request->user()->tenant_id,
                $request->input('from'),
                $request->input('to'),
            )
        );
    }

    /**
     * 调用链详情
     */
    public function show(int $id)
    {
        return ApiResponse::success($this->tracingService->getTraceDetail($id));
    }
}
