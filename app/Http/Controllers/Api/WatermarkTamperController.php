<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseWatermark;
use App\Models\TamperEvent;
use App\Models\TamperProtectionConfig;
use App\Services\WatermarkTamperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WatermarkTamperController extends Controller
{
    public function __construct(
        protected WatermarkTamperService $watermarkTamper,
    ) {}

    // ─── 水印管理 ───

    /**
     * 为 License 嵌入水印
     * POST /api/admin/licenses/{license}/watermark
     */
    public function embedWatermark(Request $request, int $licenseId): JsonResponse
    {
        $license = License::findOrFail($licenseId);

        $sourceInfo = [];
        if ($request->filled('source_info')) {
            $sourceInfo = $request->input('source_info');
        }

        if ($license->watermark_key) {
            return ApiResponse::error('WATERMARK_EXISTS', '该 License 已存在水印', 409);
        }

        $watermark = $this->watermarkTamper->embedWatermark($license, $sourceInfo);

        return ApiResponse::created($watermark, '水印已嵌入');
    }

    /**
     * 提取 License 的水印
     * GET /api/admin/licenses/{license}/watermark
     */
    public function extractWatermark(int $licenseId): JsonResponse
    {
        $license = License::findOrFail($licenseId);

        $watermark = $this->watermarkTamper->extractWatermark($license);

        if (!$watermark) {
            return ApiResponse::success(null, '该 License 无水印信息');
        }

        return ApiResponse::success($watermark);
    }

    /**
     * 吊销水印
     * DELETE /api/admin/watermarks/{watermark}
     */
    public function revokeWatermark(int $watermarkId): JsonResponse
    {
        $watermark = LicenseWatermark::findOrFail($watermarkId);
        $this->watermarkTamper->revokeWatermark($watermark);

        // 同步清理 License 上的水印引用
        if ($watermark->license) {
            $watermark->license->update(['watermark_key' => null]);
        }

        return ApiResponse::success(null, '水印已吊销');
    }

    /**
     * 根据水印追踪来源
     * GET /api/admin/watermarks/trace?key=xxx
     */
    public function traceWatermark(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $trace = $this->watermarkTamper->traceByWatermark($request->input('key'));

        if (!$trace) {
            return ApiResponse::success(null, '未找到匹配的水印');
        }

        return ApiResponse::success($trace);
    }

    /**
     * 搜索水印
     * GET /api/admin/watermarks/search?q=xxx
     */
    public function searchWatermarks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('请输入搜索关键词', $validator->errors()->toArray());
        }

        $results = $this->watermarkTamper->searchWatermarks($request->input('q'));

        return ApiResponse::success($results);
    }

    /**
     * 水印列表
     * GET /api/admin/watermarks
     */
    public function watermarks(Request $request): JsonResponse
    {
        $query = LicenseWatermark::with('license.customer')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return ApiResponse::paginated($query->paginate($perPage));
    }

    // ─── 完整性验证 ───

    /**
     * 验证 License 完整性
     * POST /api/admin/licenses/{license}/verify-integrity
     */
    public function verifyIntegrity(int $licenseId): JsonResponse
    {
        $license = License::findOrFail($licenseId);
        $result = $this->watermarkTamper->verifyIntegrity($license);

        // 记录验证日志
        $this->watermarkTamper->logVerification(
            $license,
            $result['passed'] ? 'pass' : 'tamper',
            ['detail' => $result['message'], 'data' => $result]
        );

        if (!$result['passed']) {
            // 记录防篡改事件
            $this->watermarkTamper->recordTamperEvent([
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'event_type' => $result['reason'] === 'hash_mismatch' ? 'signature' : 'integrity',
                'severity' => 'high',
                'event_data' => $result,
            ]);
        }

        return ApiResponse::success($result);
    }

    /**
     * 刷新完整性哈希
     * POST /api/admin/licenses/{license}/refresh-hash
     */
    public function refreshIntegrityHash(int $licenseId): JsonResponse
    {
        $license = License::findOrFail($licenseId);
        $hash = $this->watermarkTamper->refreshIntegrityHash($license);

        return ApiResponse::success(['integrity_hash' => $hash], '完整性哈希已更新');
    }

    // ─── 验证日志 ───

    /**
     * 获取 License 的验证历史
     * GET /api/admin/licenses/{license}/verification-logs
     */
    public function verificationLogs(int $licenseId): JsonResponse
    {
        $license = License::findOrFail($licenseId);
        $logs = $this->watermarkTamper->getVerificationHistory($license);

        return ApiResponse::success($logs);
    }

    /**
     * 验证统计
     * GET /api/admin/watermark-tamper/verification-stats
     */
    public function verificationStats(): JsonResponse
    {
        $stats = $this->watermarkTamper->getVerificationStats();
        return ApiResponse::success($stats);
    }

    // ─── 防篡改事件 ───

    /**
     * 防篡改事件列表
     * GET /api/admin/tamper-events
     */
    public function tamperEvents(Request $request): JsonResponse
    {
        $filters = $request->only(['event_type', 'severity', 'license_key', 'is_resolved']);
        $events = $this->watermarkTamper->getTamperEvents($filters);

        return ApiResponse::success($events);
    }

    /**
     * 解决防篡改事件
     * POST /api/admin/tamper-events/{id}/resolve
     */
    public function resolveTamperEvent(Request $request, int $eventId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('请输入处理说明', $validator->errors()->toArray());
        }

        $event = TamperEvent::findOrFail($eventId);
        $this->watermarkTamper->resolveTamperEvent($event, $request->input('resolution'));

        return ApiResponse::success($event->fresh()->load('resolver'), '事件已处理');
    }

    // ─── 防篡改策略管理 ───

    /**
     * 策略列表
     * GET /api/admin/tamper-policies
     */
    public function tamperPolicies(): JsonResponse
    {
        $this->watermarkTamper->seedDefaultPolicies();
        $policies = TamperProtectionConfig::orderBy('rule_type')->orderBy('severity')->get();

        return ApiResponse::success($policies);
    }

    /**
     * 更新策略
     * PUT /api/admin/tamper-policies/{id}
     */
    public function updateTamperPolicy(Request $request, int $policyId): JsonResponse
    {
        $policy = TamperProtectionConfig::findOrFail($policyId);

        $validator = Validator::make($request->all(), [
            'is_active' => 'sometimes|boolean',
            'severity' => 'sometimes|string|in:low,medium,high,critical',
            'threshold' => 'sometimes|integer|min:1|max:100',
            'cooldown_seconds' => 'sometimes|integer|min:10|max:86400',
            'description' => 'nullable|string|max:500',
            'actions' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $policy->update($validator->validated());

        return ApiResponse::success($policy->fresh(), '防篡改策略已更新');
    }

    // ─── 仪表盘 ───

    /**
     * 水印与防篡改仪表盘
     * GET /api/admin/watermark-tamper/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->watermarkTamper->getDashboardData();
        return ApiResponse::success($data);
    }
}
