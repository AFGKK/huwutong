<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SlaBreach;
use App\Models\SlaCompensation;
use App\Models\SlaContract;
use App\Models\SlaMetric;
use App\Services\SlaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlaController extends Controller
{
    public function __construct(
        protected SlaService $slaService
    ) {}

    // ─── 概览 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success($this->slaService->getDashboard($request->user()->tenant_id));
    }

    // ─── 合约 CRUD ───

    public function index(Request $request)
    {
        return ApiResponse::success($this->slaService->getContracts(
            $request->user()->tenant_id,
            $request->only(['level', 'is_active', 'customer_id'])
        ));
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->slaService->getContract($id));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'level' => 'nullable|string|in:standard,premium,enterprise,custom',
            'description' => 'nullable|string',
            'scope' => 'nullable|array',
            'terms' => 'nullable|array',
            'penalties' => 'nullable|array',
            'business_hours' => 'nullable|array',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_active' => 'nullable|boolean',
            'is_template' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->slaService->createContract($data), 201);
    }

    public function update(Request $request, SlaContract $slaContract)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'level' => 'nullable|string|in:standard,premium,enterprise,custom',
            'scope' => 'nullable|array',
            'terms' => 'nullable|array',
            'penalties' => 'nullable|array',
            'business_hours' => 'nullable|array',
            'expiry_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        return ApiResponse::success($this->slaService->updateContract($slaContract, $request->all()));
    }

    public function destroy(SlaContract $slaContract)
    {
        $this->slaService->deleteContract($slaContract);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 从模板创建 ───

    public function createFromTemplate(Request $request, int $templateId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'effective_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $overrides = $request->all();
        $overrides['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->slaService->createFromTemplate($templateId, $overrides), 201);
    }

    // ─── 指标 ───

    public function storeMetric(Request $request, SlaContract $slaContract)
    {
        $validator = Validator::make($request->all(), [
            'metric_key' => 'required|string|in:response_time,resolution_time,uptime,availability,ticket_backlog',
            'name' => 'required|string|max:200',
            'unit' => 'nullable|string|in:minutes,hours,percentage,count',
            'target_value' => 'required|numeric|min:0',
            'warning_threshold' => 'nullable|numeric|between:0,100',
            'measurement_window' => 'nullable|string|in:daily,weekly,monthly,quarterly',
            'data_source' => 'nullable|string|in:tickets,support,uptime,custom',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        return ApiResponse::success($this->slaService->addMetric($slaContract->id, $request->all()), 201);
    }

    public function updateMetric(Request $request, SlaMetric $slaMetric)
    {
        $data = $request->only(['target_value', 'warning_threshold', 'is_active', 'name']);
        return ApiResponse::success($this->slaService->updateMetric($slaMetric, $data));
    }

    public function destroyMetric(SlaMetric $slaMetric)
    {
        $this->slaService->deleteMetric($slaMetric);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 达标计算 ───

    public function calculateCompliance(Request $request, SlaContract $slaContract, SlaMetric $slaMetric)
    {
        $start = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->input('end_date', now()->format('Y-m-d'));
        $period = $request->input('period', 'daily');

        return ApiResponse::success(
            $this->slaService->calculateCompliance(
                $slaContract, $slaMetric,
                Carbon::parse($start), Carbon::parse($end), $period
            )
        );
    }

    // ─── 违约管理 ───

    public function breaches(Request $request)
    {
        return ApiResponse::success($this->slaService->getBreaches(
            $request->user()->tenant_id,
            $request->only(['severity', 'status', 'breach_type', 'page', 'per_page'])
        ));
    }

    public function acknowledgeBreach(SlaBreach $slaBreach)
    {
        return ApiResponse::success($this->slaService->acknowledgeBreach($slaBreach));
    }

    public function resolveBreach(Request $request, SlaBreach $slaBreach)
    {
        return ApiResponse::success(
            $this->slaService->resolveBreach($slaBreach, $request->input('notes'))
        );
    }

    // ─── 报表 ───

    public function complianceReport(Request $request, SlaContract $slaContract)
    {
        return ApiResponse::success(
            $this->slaService->getComplianceReport(
                $slaContract->id,
                $request->input('period', 'monthly'),
                $request->input('months', 6)
            )
        );
    }

    // ─── 元数据 ───

    public function metricKeys()
    {
        return ApiResponse::success(SlaMetric::METRIC_KEYS);
    }

    public function levels()
    {
        return ApiResponse::success(SlaContract::LEVELS);
    }

    // ═══════════ SLA 违约补偿 ═══════════

    public function compensations(Request $request)
    {
        return ApiResponse::success(
            $this->slaService->getCompensations(
                $request->user()->tenant_id,
                $request->only(['status', 'severity', 'compensation_type']),
                $request->input('per_page', 20)
            )
        );
    }

    public function compensationStats(Request $request)
    {
        return ApiResponse::success(
            $this->slaService->getCompensationStats($request->user()->tenant_id)
        );
    }

    public function autoGenerateCompensations(Request $request)
    {
        $generated = $this->slaService->autoGenerateForOpenBreaches($request->user()->tenant_id);
        return ApiResponse::success([
            'generated' => count($generated),
            'compensations' => $generated,
        ]);
    }

    public function approveCompensation(Request $request, SlaCompensation $slaCompensation)
    {
        return ApiResponse::success(
            $this->slaService->approveCompensation($slaCompensation->id, $request->user()->id)
        );
    }

    public function issueCompensation(SlaCompensation $slaCompensation)
    {
        return ApiResponse::success(
            $this->slaService->issueCompensation($slaCompensation->id)
        );
    }

    public function rejectCompensation(Request $request, SlaCompensation $slaCompensation)
    {
        return ApiResponse::success(
            $this->slaService->rejectCompensation($slaCompensation->id, $request->input('reason'))
        );
    }
}
