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

class WatermarkController extends Controller
{
    public function __construct(
        protected WatermarkTamperService $service,
    ) {}

    // ═══════════ 仪表盘 ═══════════

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboardData());
    }

    // ═══════════ 水印管理 ═══════════

    public function watermarks(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listWatermarks(
            $request->only(['status', 'algorithm', 'search']),
            $request->input('per_page', 20)
        ));
    }

    public function showWatermark(LicenseWatermark $watermark): JsonResponse
    {
        $watermark->load(['license.customer']);
        return ApiResponse::success($watermark);
    }

    public function embedWatermark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
            'algorithm' => 'nullable|in:stealth,hmac,bloom,forensic_stealth',
            'embed_location' => 'nullable|string|max:50',
            'embed_type' => 'nullable|in:metadata,license_key,integrity_hash,sdk_response',
            'source_info' => 'nullable|array',
        ]);

        $license = License::findOrFail($validated['license_id']);
        $algorithm = $validated['algorithm'] ?? 'forensic_stealth';

        if ($algorithm === 'forensic_stealth') {
            $watermark = $this->service->embedForensicWatermark($license, $validated['source_info'] ?? []);
        } else {
            $watermark = $this->service->embedWatermark($license, $validated['source_info'] ?? []);
        }

        return ApiResponse::success($watermark, __('app.api.watermark.embedded'), 201);
    }

    public function extractWatermark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
        ]);

        $license = License::findOrFail($validated['license_id']);
        $result = $this->service->extractAndVerify($license);

        return ApiResponse::success($result);
    }

    public function traceWatermark(LicenseWatermark $watermark): JsonResponse
    {
        $result = $this->service->traceByWatermark($watermark->watermark_key);
        if (!$result) {
            return ApiResponse::error('NOT_FOUND', __('app.api.watermark.not_found'), 404);
        }
        return ApiResponse::success($result);
    }

    public function searchWatermarks(Request $request): JsonResponse
    {
        $request->validate(['keyword' => 'required|string|min:2']);
        return ApiResponse::success($this->service->searchWatermarks(
            $request->input('keyword'),
            $request->input('limit', 20)
        ));
    }

    public function revokeWatermark(LicenseWatermark $watermark): JsonResponse
    {
        $this->service->revokeWatermark($watermark);
        return ApiResponse::success(null, __('app.api.watermark.revoked'));
    }

    // ═══════════ 溯源审计 ═══════════

    public function traces(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listTraces(
            $request->only(['watermark_id', 'trace_type', 'confidence']),
            $request->input('per_page', 20)
        ));
    }

    public function storeTrace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'watermark_id' => 'required|integer|exists:license_watermarks,id',
            'license_id' => 'nullable|integer|exists:licenses,id',
            'trace_type' => 'required|in:manual,auto,api,webhook',
            'source' => 'nullable|string|max:100',
            'leak_url' => 'nullable|string|max:500',
            'confidence' => 'nullable|in:low,medium,high,confirmed',
            'trace_result' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['operator_id'] = auth()->id();
        $trace = $this->service->createTrace($validated);
        return ApiResponse::success($trace->load(['watermark', 'operator:id,name']), __('app.api.watermark.trace_created'), 201);
    }

    // ═══════════ 防篡改事件 ═══════════

    public function tamperEvents(Request $request): JsonResponse
    {
        $events = $this->service->getTamperEvents(
            $request->only(['event_type', 'severity', 'license_key', 'is_resolved']),
            $request->input('limit', 50)
        );
        return ApiResponse::success($events);
    }

    public function resolveTamperEvent(Request $request, TamperEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|string|max:500',
        ]);
        $this->service->resolveTamperEvent($event, $validated['resolution']);
        return ApiResponse::success(null, __('app.api.watermark.resolved'));
    }

    // ═══════════ 防篡改策略 ═══════════

    public function policies(): JsonResponse
    {
        $policies = TamperProtectionConfig::orderBy('rule_type')->get();
        return ApiResponse::success($policies);
    }

    public function updatePolicy(Request $request, TamperProtectionConfig $policy): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'boolean',
            'threshold' => 'nullable|integer|min:1',
            'cooldown_seconds' => 'nullable|integer|min:0',
            'actions' => 'nullable|array',
            'auto_recovery' => 'nullable|array',
            'severity' => 'nullable|in:low,medium,high,critical',
        ]);

        $policy->update($validated);
        return ApiResponse::success($policy, __('app.api.watermark.policy_updated'));
    }

    // ═══════════ 验证统计 ═══════════

    public function verificationStats(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getVerificationStats(
            $request->input('days', 30)
        ));
    }

    // ═══════════ M3-10 增强端点 ═══════════

    /**
     * 批量嵌入暗水印
     */
    public function batchEmbed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_ids' => 'required|array|min:1|max:100',
            'license_ids.*' => 'integer|exists:licenses,id',
            'source_info' => 'nullable|array',
        ]);

        $results = $this->service->batchEmbedForensic(
            $validated['license_ids'],
            $validated['source_info'] ?? []
        );

        return ApiResponse::success($results, __('app.api.watermark.batch_embedded'));
    }

    /**
     * 批量提取暗水印
     */
    public function batchExtract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_ids' => 'required|array|min:1|max:100',
            'license_ids.*' => 'integer|exists:licenses,id',
        ]);

        $results = $this->service->batchExtractForensic($validated['license_ids']);
        return ApiResponse::success($results, __('app.api.watermark.batch_extracted'));
    }

    /**
     * 水印统计报表
     */
    public function report(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getWatermarkReport(
            $request->input('days', 30)
        ));
    }

    /**
     * 验证分析
     */
    public function verificationAnalysis(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getVerificationAnalysis(
            $request->input('days', 30)
        ));
    }

    /**
     * 泄漏扫描
     */
    public function leakScan(Request $request, LicenseWatermark $watermark): JsonResponse
    {
        $result = $this->service->scanForLeaks($watermark->watermark_key);
        return ApiResponse::success($result, __('app.api.watermark.leak_scan_done'));
    }

    /**
     * 水印审计报告
     */
    public function auditReport(LicenseWatermark $watermark): JsonResponse
    {
        return ApiResponse::success($this->service->generateAuditReport($watermark->id));
    }

    /**
     * 解码暗水印载荷
     */
    public function decodePayload(LicenseWatermark $watermark): JsonResponse
    {
        return ApiResponse::success($this->service->decodeForensicPayload($watermark));
    }
}
