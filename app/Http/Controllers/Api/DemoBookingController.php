<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DemoBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 预约Demo/联系销售控制器 (M2-98)
 */
class DemoBookingController extends Controller
{
    public function __construct(
        protected DemoBookingService $demoBooking,
    ) {}

    /**
     * 提交预约（公开）
     */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:200',
            'contact_name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'phone' => 'nullable|string|max:50',
            'employee_count' => 'nullable|string|max:50',
            'product_interest' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:2000',
            'source' => 'nullable|string|max:50',
            config('demo-booking.form.honeypot', 'website_url') => 'nullable|string',
        ]);

        $result = $this->demoBooking->submit($data);
        return $result['success']
            ? ApiResponse::success(['booking' => $result['booking'] ?? null], $result['message'])
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 获取预约列表（管理）
     */
    public function index(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');
        $filters = $request->only(['status']);
        $result = $this->demoBooking->getList($filters, $request->input('per_page', 20));
        return ApiResponse::success($result);
    }

    /**
     * 更新预约状态
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|string|in:pending,contacted,scheduled,completed,converted,lost']);
        $result = $this->demoBooking->updateStatus($id, $data['status']);
        return ApiResponse::success($result);
    }

    /**
     * 获取统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->demoBooking->getStats());
    }

    /**
     * 获取 Calendly 链接
     */
    public function calendly(): JsonResponse
    {
        return ApiResponse::success([
            'link' => $this->demoBooking->getCalendlyLink(),
            'enabled' => config('demo-booking.calendly.enabled'),
        ]);
    }
}
