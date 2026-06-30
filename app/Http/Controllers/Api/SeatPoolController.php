<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\SeatAssignment;
use App\Models\SeatWaitingQueue;
use App\Services\SeatPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * License 席位池管理控制器 (M3-45)
 *
 * 浮动席位管理仪表盘/概览
 */
class SeatPoolController extends Controller
{
    public function __construct(
        protected SeatPoolService $seatPool,
    ) {}

    /**
     * 席位池仪表盘
     *
     * GET /api/admin/seat-pool/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $totalLicenses = License::where('tenant_id', $tenantId)->count();
        $poolLicenses = License::where('tenant_id', $tenantId)->whereNotNull('seats')->where('seats', '>', 0)->count();

        $stats = [
            'total_licenses' => $totalLicenses,
            'pool_enabled_licenses' => $poolLicenses,
            'total_seats' => License::where('tenant_id', $tenantId)->sum('seats'),
            'active_assignments' => SeatAssignment::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'active')->count(),
            'waiting_queue' => SeatWaitingQueue::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'waiting')->count(),
            'mode_distribution' => License::where('tenant_id', $tenantId)
                ->whereNotNull('seats')->where('seats', '>', 0)
                ->selectRaw("COALESCE(pool_mode, 'shared') as mode, COUNT(*) as count")
                ->groupBy('mode')
                ->pluck('count', 'mode')
                ->toArray(),
        ];

        return ApiResponse::success($stats);
    }

    /**
     * 席位池列表
     *
     * GET /api/admin/seat-pool/licenses
     */
    public function licenses(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $query = License::where('tenant_id', $tenantId)
            ->whereNotNull('seats')
            ->where('seats', '>', 0)
            ->withCount(['seatAssignments as active_count' => fn($q) => $q->where('status', 'active')])
            ->withCount(['seatAssignments as total_assigned']);

        if (!empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }
        if (!empty($request->pool_mode)) {
            $query->where('pool_mode', $request->pool_mode);
        }

        $perPage = $request->input('per_page', 20);
        $results = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        $results->through(function ($license) {
            $total = $license->seats ?? 0;
            $active = $license->active_count ?? 0;
            $license->utilization_percent = $total > 0 ? round(($active / $total) * 100, 1) : 0;
            $license->available = max(0, $total - $active);
            return $license;
        });

        return ApiResponse::paginated($results);
    }

    /**
     * 获取 License 席位池详情
     *
     * GET /api/admin/seat-pool/licenses/{license}
     */
    public function licenseDetail(License $license): JsonResponse
    {
        $status = $this->seatPool->getPoolStatus($license);
        $assignments = $this->seatPool->getAssignments($license, [], 50);
        $queue = $this->seatPool->getQueue($license);

        return ApiResponse::success([
            'license' => $license->load('customer:id,name'),
            'pool_status' => $status,
            'assignments' => $assignments,
            'queue' => $queue,
        ]);
    }

    /**
     * 更新席位池配置
     *
     * PUT /api/admin/seat-pool/licenses/{license}/config
     */
    public function updateConfig(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'seats' => 'nullable|integer|min:0|max:1000',
            'pool_mode' => 'nullable|in:shared,exclusive,auto',
            'pool_timeout_minutes' => 'nullable|integer|min:1|max:1440',
            'pool_waiting_limit' => 'nullable|integer|min:1|max:200',
        ]);

        $license->update($validated);

        return ApiResponse::success($license->fresh(), '席位池配置已更新');
    }

    /**
     * 批量清理过期席位
     *
     * POST /api/admin/seat-pool/batch-release-expired
     */
    public function batchReleaseExpired(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $results = $this->seatPool->batchReleaseExpiredSeats($tenantId);

        return ApiResponse::success($results, '过期席位清理完成');
    }

    /**
     * 席位分配历史
     *
     * GET /api/admin/seat-pool/assignment-history
     */
    public function assignmentHistory(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $query = SeatAssignment::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['license:id,license_key', 'device:id,fingerprint,platform'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('license_id')) {
            $query->where('license_id', $request->license_id);
        }

        $perPage = $request->input('per_page', 20);
        return ApiResponse::paginated($query->paginate($perPage)->withQueryString());
    }

    // ─── License 级操作 ───

    public function licensePoolStatus(License $license): JsonResponse
    {
        return ApiResponse::success($this->seatPool->getPoolStatus($license));
    }

    public function licenseAssignments(License $license, Request $request): JsonResponse
    {
        $assignments = SeatAssignment::where('license_id', $license->id)
            ->with('device:id,fingerprint,platform,last_ip')
            ->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 20), 100));
        return ApiResponse::paginated($assignments);
    }

    public function licenseQueue(License $license): JsonResponse
    {
        $queue = SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')
            ->orderBy('id')
            ->get();
        return ApiResponse::success($queue);
    }

    public function licenseAssign(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'seat_identifier' => 'nullable|string|max:255',
        ]);

        $result = $this->seatPool->assignSeat($license, $validated['device_id'], $validated['seat_identifier'] ?? null);

        if (!$result) {
            return ApiResponse::error('NO_AVAILABLE_SEAT', '无可用的席位', 409);
        }

        return ApiResponse::success($result, '席位分配成功');
    }

    public function licenseRelease(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'seat_identifier' => 'required|string|max:255',
        ]);

        $this->seatPool->releaseSeat($license, $validated['seat_identifier']);

        return ApiResponse::success(null, '席位已释放');
    }

    public function licenseHeartbeat(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'seat_identifier' => 'required|string|max:255',
        ]);

        $this->seatPool->heartbeat((int) $license->id, $validated['seat_identifier']);

        return ApiResponse::success(null, '心跳已更新');
    }

    public function licenseCancelQueue(License $license): JsonResponse
    {
        SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')
            ->update(['status' => 'cancelled']);

        return ApiResponse::success(null, '排队已取消');
    }

    public function licensePoolConfig(License $license): JsonResponse
    {
        return ApiResponse::success([
            'pool_mode' => $license->pool_mode ?? 'shared',
            'total_seats' => (int) ($license->seats ?? 0),
            'assigned_seats' => SeatAssignment::where('license_id', $license->id)->where('status', 'active')->count(),
            'waiting_count' => SeatWaitingQueue::where('license_id', $license->id)->where('status', 'waiting')->count(),
        ]);
    }
}
