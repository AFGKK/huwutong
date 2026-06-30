<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlertManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 告警聚合与疲劳管理 (M2-119)
 */
class AlertManagerController extends Controller
{
    public function __construct(
        protected AlertManagerService $manager,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->dashboard()]);
    }

    // ─── 聚合 ───

    public function aggregate(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->aggregateEvents()]);
    }

    public function aggregationGroups(Request $request): JsonResponse
    {
        $hours = min((int) $request->input('hours', 24), 168);
        return response()->json(['success' => true, 'data' => $this->manager->aggregationGroups($hours)]);
    }

    public function aggregationDetail(string $groupKey): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->aggregationDetail($groupKey)]);
    }

    // ─── 静默规则 ───

    public function listSilenceRules(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->listSilenceRules($request)]);
    }

    public function storeSilenceRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'match_type' => 'required|in:exact,pattern,wildcard',
            'match_rules' => 'nullable|array',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'timezone' => 'nullable|string|max:50',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|max:100',
            'reason' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->storeSilenceRule($validated)], 201);
    }

    public function updateSilenceRule(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'match_type' => 'sometimes|in:exact,pattern,wildcard',
            'match_rules' => 'nullable|array',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'is_active' => 'sometimes|boolean',
            'reason' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->updateSilenceRule($id, $validated)]);
    }

    public function deleteSilenceRule(int $id): JsonResponse
    {
        $this->manager->deleteSilenceRule($id);
        return response()->json(['success' => true, 'message' => '已删除']);
    }

    public function toggleSilenceRule(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->toggleSilenceRule($id)]);
    }

    // ─── 疲劳管理 ───

    public function checkFatigue(int $ruleId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->checkFatigue($ruleId)]);
    }

    public function autoDowngrade(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->autoDowngrade()]);
    }

    public function listFatigueSettings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->listFatigueSettings()]);
    }

    public function storeFatigueSetting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'nullable|string|max:50',
            'repetition_threshold' => 'required|integer|min:1|max:100',
            'decay_factor' => 'required|numeric|min:0|max:1',
            'auto_downgrade' => 'nullable|boolean',
            'target_severity' => 'nullable|in:info,warning,critical',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->storeFatigueSetting($validated)], 201);
    }

    public function updateFatigueSetting(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repetition_threshold' => 'sometimes|integer|min:1|max:100',
            'decay_factor' => 'sometimes|numeric|min:0|max:1',
            'auto_downgrade' => 'nullable|boolean',
            'target_severity' => 'nullable|in:info,warning,critical',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->updateFatigueSetting($id, $validated)]);
    }

    public function deleteFatigueSetting(int $id): JsonResponse
    {
        $this->manager->deleteFatigueSetting($id);
        return response()->json(['success' => true, 'message' => '已删除']);
    }

    // ─── 摘要 & 分析 ───

    public function generateDigest(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->generateDigest()]);
    }

    public function noiseAnalysis(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 7), 90);
        return response()->json(['success' => true, 'data' => $this->manager->noiseAnalysis($days)]);
    }

    public function notificationStats(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 7), 90);
        return response()->json(['success' => true, 'data' => $this->manager->notificationStats($days)]);
    }
}
