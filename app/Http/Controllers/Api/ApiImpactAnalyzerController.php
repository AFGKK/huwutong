<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiImpactAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API 变更影响分析 (M2-111)
 */
class ApiImpactAnalyzerController extends Controller
{
    public function __construct(
        protected ApiImpactAnalyzerService $analyzer,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analyzer->dashboard()]);
    }

    public function analyzeVersion(int $versionId, Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365);
        return response()->json(['success' => true, 'data' => $this->analyzer->analyzeVersion($versionId, $days)]);
    }

    public function overallReport(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365);
        return response()->json(['success' => true, 'data' => $this->analyzer->overallReport($days)]);
    }

    public function customerVersionUsage(int $tenantId, Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 90), 365);
        return response()->json(['success' => true, 'data' => $this->analyzer->customerVersionUsage($tenantId, $days)]);
    }

    public function sendNotifications(int $versionId, Request $request): JsonResponse
    {
        $channel = $request->input('channel', 'email');
        return response()->json(['success' => true, 'data' => $this->analyzer->sendNotifications($versionId, $channel)]);
    }

    public function notificationHistory(int $versionId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analyzer->notificationHistory($versionId)]);
    }

    public function exportReport(int $versionId, Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365);
        $data = $this->analyzer->exportReport($versionId, $days);
        return response()->json(['success' => true, 'data' => $data]);
    }
}
