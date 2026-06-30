<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\LicenseTrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseTrashController extends Controller
{
    public function __construct(protected LicenseTrashService $trashService) {}

    /**
     * 回收站列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->trashService->getTrashed($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * 回收站统计
     */
    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->trashService->getStats($request->user()->tenant_id)
        );
    }

    /**
     * 恢复单个 License
     */
    public function restore(int $id): JsonResponse
    {
        $result = $this->trashService->restore($id);
        return ApiResponse::success(null, $result['message']);
    }

    /**
     * 批量恢复
     */
    public function batchRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:licenses,id',
        ]);

        $result = $this->trashService->batchRestore(
            $request->user()->tenant_id,
            $validated['ids']
        );

        return ApiResponse::success(null, $result['message']);
    }

    /**
     * 永久删除
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->trashService->forceDelete($id);
        return ApiResponse::success(null, $result['message']);
    }

    /**
     * 清空回收站
     */
    public function clear(Request $request): JsonResponse
    {
        $result = $this->trashService->clearTrash($request->user()->tenant_id);
        return ApiResponse::success(null, $result['message']);
    }
}
