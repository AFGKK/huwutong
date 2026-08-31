<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseSnapshot;
use App\Services\LicenseSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseSnapshotController extends Controller
{
    public function __construct(protected LicenseSnapshotService $snapshotService) {}

    /**
     * 快照列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->snapshotService->getSnapshots(
                $request->user()->tenant_id,
                $request->input('license_id'),
                $request->all()
            )
        );
    }

    /**
     * 仪表盘统计
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->snapshotService->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 手动创建快照
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
            'action'     => 'nullable|string|max:50',
        ]);

        $license = License::findOrFail($validated['license_id']);
        $action = $validated['action'] ?? 'manual';

        $snapshot = $this->snapshotService->createSnapshot($license, $action, $request->user());

        return ApiResponse::created($snapshot, __("app.license_snapshot.msg_c1deab42"));
    }

    /**
     * 查看快照详情
     */
    public function show(int $id): JsonResponse
    {
        $snapshot = LicenseSnapshot::with(['license' => fn($q) => $q->withTrashed()])->findOrFail($id);
        return ApiResponse::success($snapshot);
    }

    /**
     * 回滚到指定快照
     */
    public function rollback(Request $request, int $id): JsonResponse
    {
        $snapshot = LicenseSnapshot::findOrFail($id);
        $license = License::withTrashed()->findOrFail($snapshot->license_id);

        $result = $this->snapshotService->rollback($license, $snapshot, $request->user());

        return $result['success']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error($result['message'], 400);
    }
}
