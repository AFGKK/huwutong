<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AIFraudDetector;
use App\Services\BehaviorEngine;
use App\Models\License;
use App\Models\AuditAnomaly;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI 风控 & 行为风控 (M3-01, M3-02)
 */
class FraudRiskController extends Controller
{
    public function __construct(
        protected AIFraudDetector $fraudDetector,
        protected BehaviorEngine $behaviorEngine,
    ) {}

    /**
     * 对指定 License 执行风控评估
     */
    public function evaluateLicense(License $license): JsonResponse
    {
        $context = [
            'ip' => request()->ip(),
            'device_fingerprint' => request()->input('device_fingerprint', ''),
            'country' => request()->input('country', ''),
            'timestamp' => time(),
        ];

        $result = $this->fraudDetector->evaluateActivation($license, $context);
        return ApiResponse::success($result);
    }

    /**
     * 批量风控评估
     */
    public function batchEvaluate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $results = $this->fraudDetector->batchEvaluate($tenantId);
        return ApiResponse::success($results);
    }

    /**
     * 风控统计
     */
    public function fraudStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->fraudDetector->getStats($tenantId));
    }

    /**
     * 异常记录列表
     */
    public function anomalies(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $query = AuditAnomaly::with('license')
            ->where('anomaly_type', 'activation_risk')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('detected_at');

        if ($request->filled('risk_level')) {
            $query->where('severity', $request->risk_level);
        }

        return ApiResponse::paginated($query->paginate($request->input('per_page', 20)));
    }

    /**
     * 行为分析
     */
    public function analyze(Request $request): JsonResponse
    {
        $context = [
            'ip' => $request->ip(),
            'license_key' => $request->input('license_key', ''),
            'device_fingerprint' => $request->input('device_fingerprint', ''),
            'endpoint' => $request->input('endpoint', 'activate'),
        ];

        $result = $this->behaviorEngine->analyze($context['endpoint'], $context);
        return ApiResponse::success($result);
    }

    /**
     * 检查封禁状态
     */
    public function checkBan(Request $request): JsonResponse
    {
        $ip = $request->ip();
        $deviceFingerprint = $request->input('device_fingerprint', '');

        return ApiResponse::success([
            'ip_banned' => $this->behaviorEngine->isIpBanned($ip),
            'device_banned' => $deviceFingerprint ? $this->behaviorEngine->isDeviceBanned($deviceFingerprint) : false,
        ]);
    }

    /**
     * 手动解封
     */
    public function unban(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:ip,device',
            'value' => 'required|string',
        ]);

        $success = $this->behaviorEngine->unban($request->type, $request->value);
        return $success
            ? ApiResponse::success(null, '解封成功')
            : ApiResponse::error('UNBAN_FAILED', '未找到封禁记录', 404);
    }

    /**
     * 行为引擎统计
     */
    public function behaviorStats(): JsonResponse
    {
        return ApiResponse::success($this->behaviorEngine->getStats());
    }
}
