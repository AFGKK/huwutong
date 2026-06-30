<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SlaProbe;
use App\Services\SlaProbeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlaProbeController extends Controller
{
    public function __construct(
        protected SlaProbeService $probeService,
    ) {}

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->probeService->getDashboard($request->user()->tenant_id)
        );
    }

    // ─── 拨测配置 CRUD ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->probeService->listProbes(
                $request->user()->tenant_id,
                $request->only(['search', 'status', 'is_active', 'per_page'])
            )
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'url' => 'required|url|max:500',
            'method' => 'nullable|string|in:GET,POST,PUT,HEAD,DELETE',
            'headers' => 'nullable|array',
            'body' => 'nullable|string',
            'expected_status' => 'nullable|string|max:10',
            'expected_body_contains' => 'nullable|string|max:500',
            'timeout_seconds' => 'nullable|integer|min:1|max:120',
            'interval_minutes' => 'nullable|integer|min:1|max:1440',
            'sla_targets' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->probeService->createProbe($data), 201);
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->probeService->getProbe($id));
    }

    public function update(Request $request, SlaProbe $slaProbe)
    {
        $data = $request->only([
            'name', 'url', 'method', 'headers', 'body',
            'expected_status', 'expected_body_contains',
            'timeout_seconds', 'interval_minutes', 'sla_targets', 'is_active',
        ]);

        return ApiResponse::success($this->probeService->updateProbe($slaProbe, $data));
    }

    public function destroy(SlaProbe $slaProbe)
    {
        $this->probeService->deleteProbe($slaProbe);
        return ApiResponse::success(['deleted' => true]);
    }

    public function toggle(SlaProbe $slaProbe)
    {
        return ApiResponse::success($this->probeService->toggleProbe($slaProbe));
    }

    // ─── 手动执行拨测 ───

    public function runNow(SlaProbe $slaProbe)
    {
        $result = $this->probeService->probe($slaProbe);
        return ApiResponse::success([
            'result' => $result,
            'status' => $result->status,
            'response_time_ms' => $result->response_time_ms,
        ]);
    }

    // ─── 拨测结果 ───

    public function results(Request $request, SlaProbe $slaProbe)
    {
        return ApiResponse::success(
            $this->probeService->getResults(
                $slaProbe->id,
                $request->only(['status', 'from', 'to', 'per_page'])
            )
        );
    }

    // ─── 可用性统计 ───

    public function uptime(Request $request, SlaProbe $slaProbe)
    {
        return ApiResponse::success(
            $this->probeService->getUptimeStats(
                $slaProbe->id,
                $request->input('period', 'daily'),
                (int) $request->input('days', 30),
            )
        );
    }
}
