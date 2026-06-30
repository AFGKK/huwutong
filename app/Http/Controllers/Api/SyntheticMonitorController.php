<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyntheticMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 合成监控多区域拨测 (M2-120)
 */
class SyntheticMonitorController extends Controller
{
    public function __construct(
        protected SyntheticMonitorService $monitor,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->monitor->dashboard()]);
    }

    public function listRegions(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->monitor->listRegions()]);
    }

    public function seedRegions(): JsonResponse
    {
        $this->monitor->seedRegions();
        return response()->json(['success' => true, 'data' => $this->monitor->listRegions()]);
    }

    public function createProbe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'url' => 'required|url|max:500',
            'method' => 'required|in:GET,POST,PUT,HEAD,DELETE',
            'regions' => 'nullable|array',
            'regions.*' => 'string|in:ap-asia,eu-europe,us-north-america',
            'headers' => 'nullable|array',
            'body' => 'nullable|string',
            'expected_status' => 'nullable|integer|min:100|max:599',
            'timeout_seconds' => 'nullable|integer|min:5|max:120',
            'interval_minutes' => 'nullable|integer|min:1|max:1440',
            'run_immediately' => 'nullable|boolean',
        ]);

        $probe = $this->monitor->createProbe($validated);
        return response()->json(['success' => true, 'data' => $probe], 201);
    }

    public function listProbes(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->monitor->listProbes($request)]);
    }

    public function regionStats(string $regionCode, Request $request): JsonResponse
    {
        $hours = min((int) $request->input('hours', 24), 168);
        return response()->json(['success' => true, 'data' => $this->monitor->regionStats($regionCode, $hours)]);
    }

    public function allRegionComparison(Request $request): JsonResponse
    {
        $hours = min((int) $request->input('hours', 24), 168);
        return response()->json(['success' => true, 'data' => $this->monitor->allRegionComparison($hours)]);
    }

    public function slaReport(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);
        return response()->json(['success' => true, 'data' => $this->monitor->slaReport($days)]);
    }

    public function syncToStatusPage(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->monitor->syncToStatusPage()]);
    }

    public function pruneResults(): JsonResponse
    {
        $deleted = $this->monitor->pruneResults();
        return response()->json(['success' => true, 'data' => ['deleted' => $deleted]]);
    }
}
