<?php

namespace App\Http\Controllers\Api;

use App\Enums\LicenseStatus;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\TwoPhaseCommitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoPhaseCommitController extends Controller
{
    public function __construct(
        protected TwoPhaseCommitService $twoPhaseCommit,
    ) {}

    /**
     * Phase 1: 预申请授权
     *
     * POST /api/license/reserve
     * 客户端先锁定一个可用slot，返回 reservation_token
     */
    public function reserve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'fingerprint' => 'nullable|string',
            'components' => 'nullable|array',
            'components.mac' => 'nullable|string',
            'components.cpu_id' => 'nullable|string',
            'components.motherboard' => 'nullable|string',
            'components.disk_sn' => 'nullable|string',
            'components.system_uuid' => 'nullable|string',
            'platform' => 'nullable|string',
            'os_version' => 'nullable|string',
            'payload' => 'nullable|array',
        ]);

        $license = License::where('license_key', $data['license_key'])->first();

        if (!$license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        $result = $this->twoPhaseCommit->reserve($license, [
            'fingerprint' => $data['fingerprint'] ?? null,
            'ip_address' => $request->ip(),
            'payload' => $data['payload'] ?? [
                'components' => $data['components'] ?? null,
                'platform' => $data['platform'] ?? null,
                'os_version' => $data['os_version'] ?? null,
            ],
        ]);

        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'RESERVE_FAILED',
                $result['message'] ?? '预申请失败',
                422,
                $result,
            );
        }

        $reservation = $result['reservation'];

        return ApiResponse::success([
            'reservation_token' => $reservation->reservation_token,
            'expires_at' => $reservation->expires_at,
            'ttl_seconds' => (int) now()->diffInSeconds($reservation->expires_at, false),
            'is_existing' => $result['is_existing'] ?? false,
        ], '授权预申请成功，请在过期前提交确认');
    }

    /**
     * Phase 2: 确认提交预留
     *
     * POST /api/license/commit
     * 客户端确认使用预留slot，完成最终激活
     */
    public function commit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reservation_token' => 'required|string',
        ]);

        $result = $this->twoPhaseCommit->commit($data['reservation_token']);

        if (!$result['success']) {
            $statusCode = match ($result['error'] ?? '') {
                'RESERVATION_NOT_FOUND' => 404,
                'RESERVATION_EXPIRED' => 410,
                default => 422,
            };
            return ApiResponse::error(
                $result['error'] ?? 'COMMIT_FAILED',
                $result['message'] ?? '提交确认失败',
                $statusCode,
            );
        }

        $license = $result['license'];

        return ApiResponse::success([
            'valid' => true,
            'license_key' => $license->license_key,
            'status' => $license->status,
            'expires_at' => $license->expires_at,
        ], '授权确认成功');
    }

    /**
     * 取消预留
     *
     * POST /api/license/cancel-reservation
     */
    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reservation_token' => 'required|string',
        ]);

        $result = $this->twoPhaseCommit->cancel($data['reservation_token']);

        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'CANCEL_FAILED',
                $result['message'] ?? '取消预留失败',
                404,
            );
        }

        return ApiResponse::success(null, '预留已取消');
    }

    /**
     * 查询预留状态
     *
     * POST /api/license/reservation-status
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reservation_token' => 'required|string',
        ]);

        $result = $this->twoPhaseCommit->getStatus($data['reservation_token']);

        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'NOT_FOUND',
                $result['message'] ?? '预留不存在',
                404,
            );
        }

        return ApiResponse::success([
            'status' => $result['reservation']->status,
            'is_valid' => $result['is_valid'],
            'is_expired' => $result['is_expired'],
            'seconds_remaining' => $result['seconds_remaining'],
            'expires_at' => $result['reservation']->expires_at,
            'logs' => $result['reservation']->logs,
        ]);
    }

    // ─── 管理端 API ─────────────────────────────────────

    /**
     * 获取 License 的预留统计
     */
    public function stats(Request $request, License $license): JsonResponse
    {
        return ApiResponse::success(
            $this->twoPhaseCommit->getReservationStats($license)
        );
    }

    /**
     * 获取 active 预留列表
     */
    public function activeReservations(Request $request): JsonResponse
    {
        $reservations = $this->twoPhaseCommit->getActiveReservations(
            $request->user()->tenant_id,
            min((int) $request->get('per_page', 20), 100),
        );

        return ApiResponse::paginated($reservations);
    }

    /**
     * 获取预留历史
     */
    public function history(Request $request): JsonResponse
    {
        $reservations = $this->twoPhaseCommit->getReservationHistory(
            $request->user()->tenant_id,
            $request->only(['status', 'license_id', 'fingerprint']),
            min((int) $request->get('per_page', 20), 100),
        );

        return ApiResponse::paginated($reservations);
    }
}
