<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ChurnIntervention;
use App\Services\ChurnPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChurnPredictionController extends Controller
{
    public function __construct(
        protected ChurnPredictionService $service
    ) {}

    // ─── 流失预测清单 ───

    public function churnList(Request $request)
    {
        return ApiResponse::success(
            $this->service->getChurnList(
                $request->user()->tenant_id,
                $request->only(['risk_level', 'health_grade', 'search', 'per_page'])
            )
        );
    }

    // ─── 干预管理 ───

    public function interventions(Request $request)
    {
        return ApiResponse::success(
            $this->service->listInterventions(
                $request->user()->tenant_id,
                $request->only(['status', 'type', 'customer_id', 'per_page'])
            )
        );
    }

    public function storeIntervention(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer',
            'type' => 'required|string|in:' . implode(',', array_keys(ChurnIntervention::TYPES)),
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createIntervention($data), 201);
    }

    public function updateIntervention(Request $request, ChurnIntervention $churnIntervention)
    {
        return ApiResponse::success($this->service->updateIntervention($churnIntervention, $request->all()));
    }

    public function deleteIntervention(ChurnIntervention $churnIntervention)
    {
        $this->service->deleteIntervention($churnIntervention);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    public function trend(Request $request)
    {
        return ApiResponse::success(
            $this->service->getTrend(
                $request->user()->tenant_id,
                $request->input('months', 12)
            )
        );
    }
}
