<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDataExport;
use App\Services\CustomerDataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerDataExportController extends Controller
{
    public function __construct(
        protected CustomerDataExportService $exportService
    ) {}

    // ─── 客户门户端点 ───

    /**
     * 获取可导出的数据类型及数量
     */
    public function availableTypes(Request $request): JsonResponse
    {
        $customer = $this->getPortalCustomer($request);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json($this->exportService->getAvailableTypes($customer));
    }

    /**
     * 创建导出请求
     */
    public function createExport(Request $request): JsonResponse
    {
        $customer = $this->getPortalCustomer($request);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:' . implode(',', CustomerDataExport::TYPES),
            'format' => 'nullable|string|in:' . implode(',', CustomerDataExport::FORMATS),
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|string',
            'filters.type' => 'nullable|string',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date|after_or_equal:filters.date_from',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => __('app.common.validation_failed'), 'errors' => $validator->errors()], 422);
        }

        // 限制频率：同一客户同类型每60秒只能导一次
        $recent = CustomerDataExport::where('customer_id', $customer->id)
            ->where('type', $request->input('type'))
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();

        if ($recent) {
            return response()->json(['message' => __('app.controller_compat.customer_data_export_60')], 429);
        }

        $export = $this->exportService->createExport(
            $customer,
            $request->input('type'),
            $request->input('format', 'csv'),
            $request->input('filters', [])
        );

        return response()->json([
            'message' => __('app.controller_compat.customer_data_export_msg_76'),
            'export' => $export,
        ], 201);
    }

    /**
     * 获取导出历史
     */
    public function myExports(Request $request): JsonResponse
    {
        $customer = $this->getPortalCustomer($request);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $exports = $this->exportService->getExportHistory($customer);
        return response()->json($exports);
    }

    /**
     * 下载导出文件
     */
    public function downloadExport(int $id, Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $customer = $this->getPortalCustomer($request);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $export = CustomerDataExport::where('id', $id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $file = $this->exportService->download($export);
        if (!$file) {
            return response()->json(['message' => __('app.controller_compat.customer_data_export_msg_111')], 410);
        }

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="' . $file['name'] . '"',
            'Content-Length' => strlen($file['content']),
        ]);
    }

    /**
     * 删除导出记录
     */
    public function deleteExport(int $id, Request $request): JsonResponse
    {
        $customer = $this->getPortalCustomer($request);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $export = CustomerDataExport::where('id', $id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $export->delete();
        return response()->json(['message' => __('app.common.deleted')]);
    }

    // ─── 管理员端点 ───

    /**
     * 管理员: 客户导出列表
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = CustomerDataExport::with('customer:id,name,email');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        $perPage = $request->input('per_page', 15);
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    /**
     * 管理员: 统计数据
     */
    public function adminStats(): JsonResponse
    {
        return response()->json($this->exportService->getStats());
    }

    /**
     * 管理员: 为指定客户创建导出
     */
    public function adminCreateExport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|string|in:' . implode(',', CustomerDataExport::TYPES),
            'format' => 'nullable|string|in:' . implode(',', CustomerDataExport::FORMATS),
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => __('app.common.validation_failed'), 'errors' => $validator->errors()], 422);
        }

        $customer = Customer::findOrFail($request->input('customer_id'));
        $export = $this->exportService->createExport(
            $customer,
            $request->input('type'),
            $request->input('format', 'csv'),
            $request->input('filters', [])
        );

        return response()->json([
            'message' => __('app.controller_compat.customer_data_export_msg_194'),
            'export' => $export,
        ], 201);
    }

    /**
     * Helper: 从请求中获取当前客户门户客户
     */
    protected function getPortalCustomer(Request $request): ?Customer
    {
        $user = $request->user();
        if (!$user) return null;
        return $user->customer;
    }
}
