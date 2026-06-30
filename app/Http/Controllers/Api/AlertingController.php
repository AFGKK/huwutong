<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\AlertEscalation;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Services\AlertingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlertingController extends Controller
{
    public function __construct(
        protected AlertingService $alertingService
    ) {}

    // ─── 概览 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success($this->alertingService->getDashboard($request->user()->tenant_id));
    }

    // ─── 告警规则 ───

    public function rules(Request $request)
    {
        return ApiResponse::success($this->alertingService->getRules(
            $request->user()->tenant_id,
            $request->only(['is_active', 'metric_type', 'severity'])
        ));
    }

    public function ruleShow(int $id)
    {
        return ApiResponse::success($this->alertingService->getRule($id));
    }

    public function ruleStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'metric_type' => 'required|string',
            'condition_operator' => 'nullable|string|in:gt,gte,lt,lte,eq,neq,pattern',
            'threshold' => 'nullable|numeric',
            'duration_minutes' => 'nullable|integer|min:0',
            'severity' => 'nullable|string|in:info,warning,critical',
            'cooldown_minutes' => 'nullable|integer|min:0',
            'max_alert_per_day' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'channels' => 'nullable|array',
            'filters' => 'nullable|array',
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'integer|exists:alert_channels,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        return ApiResponse::success($this->alertingService->createRule($request->all()), '操作成功', 201);
    }

    public function ruleUpdate(Request $request, AlertRule $alertRule)
    {
        $data = $request->only([
            'name', 'metric_type', 'condition_operator', 'threshold',
            'duration_minutes', 'severity', 'cooldown_minutes', 'max_alert_per_day',
            'is_active', 'channels', 'filters', 'webhook_urls',
            'slack_webhook', 'dingtalk_webhook', 'description', 'channel_ids',
        ]);
        return ApiResponse::success($this->alertingService->updateRule($alertRule, $data));
    }

    public function ruleDestroy(AlertRule $alertRule)
    {
        $this->alertingService->deleteRule($alertRule);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 通知渠道 ───

    public function channels(Request $request)
    {
        return ApiResponse::success($this->alertingService->getChannels($request->user()->tenant_id));
    }

    public function channelStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:email,slack,webhook,sms,dingtalk,feishu,wechat,custom',
            'config' => 'required|array',
            'is_enabled' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->alertingService->createChannel($data), '操作成功', 201);
    }

    public function channelUpdate(Request $request, AlertChannel $alertChannel)
    {
        return ApiResponse::success($this->alertingService->updateChannel(
            $alertChannel, $request->only(['name', 'config', 'is_enabled', 'is_default', 'description'])
        ));
    }

    public function channelDestroy(AlertChannel $alertChannel)
    {
        $this->alertingService->deleteChannel($alertChannel);
        return ApiResponse::success(['deleted' => true]);
    }

    public function testChannel(AlertChannel $alertChannel)
    {
        return ApiResponse::success($this->alertingService->testChannel($alertChannel));
    }

    // ─── 升级策略 ───

    public function escalations(Request $request)
    {
        return ApiResponse::success($this->alertingService->getEscalations(
            $request->user()->tenant_id,
            $request->input('alert_rule_id')
        ));
    }

    public function escalationStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'alert_rule_id' => 'nullable|integer|exists:alert_rules,id',
            'escalation_level' => 'required|integer|in:1,2,3',
            'after_minutes' => 'required|integer|min:1',
            'notify_type' => 'required|string',
            'notify_target' => 'required|array',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->alertingService->createEscalation($data), '操作成功', 201);
    }

    public function escalationUpdate(Request $request, AlertEscalation $alertEscalation)
    {
        return ApiResponse::success($this->alertingService->updateEscalation(
            $alertEscalation, $request->all()
        ));
    }

    public function escalationDestroy(int $id)
    {
        $this->alertingService->deleteEscalation($id);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 告警事件 ───

    public function events(Request $request)
    {
        return ApiResponse::success($this->alertingService->getEvents(
            $request->user()->tenant_id,
            $request->only(['status', 'severity', 'event_type', 'page', 'per_page'])
        ));
    }

    public function eventShow(int $id)
    {
        return ApiResponse::success($this->alertingService->getEvent($id));
    }

    public function acknowledgeEvent(Request $request, AlertEvent $alertEvent)
    {
        return ApiResponse::success($this->alertingService->acknowledgeEvent(
            $alertEvent, $request->user()->id
        ));
    }

    public function resolveEvent(Request $request, AlertEvent $alertEvent)
    {
        return ApiResponse::success($this->alertingService->resolveEvent(
            $alertEvent, $request->user()->id
        ));
    }

    // ─── 统计 ───

    public function eventStats(Request $request)
    {
        return ApiResponse::success($this->alertingService->getEventStats(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d'))
        ));
    }

    // ─── 元数�?───

    public function metricTypes()
    {
        return ApiResponse::success($this->alertingService->getMetricTypes());
    }

    public function severities()
    {
        return ApiResponse::success($this->alertingService->getSeverities());
    }
}
