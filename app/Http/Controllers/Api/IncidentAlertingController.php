<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\OpsGenieService;
use App\Services\PagerDutyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PagerDuty/OpsGenie 告警集成控制器 (M2-122)
 */
class IncidentAlertingController extends Controller
{
    public function __construct(
        protected PagerDutyService $pagerDuty,
        protected OpsGenieService $opsGenie,
    ) {
    }

    /**
     * 获取集成状态
     */
    public function status(): JsonResponse
    {
        return ApiResponse::success([
            'pagerduty' => [
                'enabled' => $this->pagerDuty->isEnabled(),
                'config' => [
                    'api_endpoint' => config('incident-alerting.pagerduty.api_endpoint'),
                    'service_id' => config('incident-alerting.pagerduty.service_id'),
                    'escalation_policy_id' => config('incident-alerting.pagerduty.escalation_policy_id'),
                ],
                'auto_push' => config('incident-alerting.auto_push'),
            ],
            'opsgenie' => [
                'enabled' => $this->opsGenie->isEnabled(),
                'config' => [
                    'api_endpoint' => config('incident-alerting.opsgenie.api_endpoint'),
                    'team_id' => config('incident-alerting.opsgenie.team_id'),
                    'schedule_id' => config('incident-alerting.opsgenie.schedule_id'),
                ],
            ],
            'severity_mapping' => config('incident-alerting.severity_mapping'),
            'event_sync' => config('incident-alerting.event_sync'),
        ]);
    }

    /**
     * 测试 PagerDuty 连接
     */
    public function testPagerDuty(): JsonResponse
    {
        $result = $this->pagerDuty->testConnection();
        return $result['success']
            ? ApiResponse::success($result, 'PagerDuty 连接测试成功')
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 测试 OpsGenie 连接
     */
    public function testOpsGenie(): JsonResponse
    {
        $result = $this->opsGenie->testConnection();
        return $result['success']
            ? ApiResponse::success($result, 'OpsGenie 连接测试成功')
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 推送测试告警
     */
    public function sendTestAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|in:pagerduty,opsgenie,both',
            'severity' => 'nullable|string|in:critical,high,warning,info,low',
        ]);

        $severity = $data['severity'] ?? 'warning';
        $summary = '【互物通】这是一条测试告警 — ' . date('Y-m-d H:i:s');
        $results = [];

        if (in_array($data['channel'], ['pagerduty', 'both'])) {
            $pdSeverity = config("incident-alerting.severity_mapping.{$severity}.pagerduty", 'warning');
            $results['pagerduty'] = $this->pagerDuty->triggerAlert(
                $summary,
                $pdSeverity,
                ['component' => 'test', 'group' => 'test', 'class' => 'test'],
                'huwutong-test'
            );
        }

        if (in_array($data['channel'], ['opsgenie', 'both'])) {
            $ogPriority = config("incident-alerting.severity_mapping.{$severity}.opsgenie", 'P3');
            $results['opsgenie'] = $this->opsGenie->createAlert(
                $summary,
                $ogPriority,
                ['tags' => ['huwutong', 'test'], 'source' => 'huwutong-test']
            );
        }

        $allSuccess = collect($results)->every(fn($r) => $r['success']);
        return $allSuccess
            ? ApiResponse::success($results, '测试告警已发送')
            : ApiResponse::error($results, 400);
    }

    /**
     * 获取 PagerDuty 最近事件
     */
    public function pagerDutyEvents(): JsonResponse
    {
        $result = $this->pagerDuty->getRecentEvents();
        return $result['success']
            ? ApiResponse::success($result)
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 获取 OpsGenie 未关闭告警
     */
    public function opsGenieAlerts(): JsonResponse
    {
        $result = $this->opsGenie->getOpenAlerts();
        return $result['success']
            ? ApiResponse::success($result)
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 推送自定义告警
     */
    public function pushAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|in:pagerduty,opsgenie,both',
            'summary' => 'required|string|max:500',
            'severity' => 'required|string|in:critical,high,warning,info,low',
            'details' => 'nullable|array',
        ]);

        $details = $data['details'] ?? [];
        $results = [];

        if (in_array($data['channel'], ['pagerduty', 'both'])) {
            $pdSeverity = config("incident-alerting.severity_mapping.{$data['severity']}.pagerduty", 'warning');
            $results['pagerduty'] = $this->pagerDuty->triggerAlert(
                $data['summary'],
                $pdSeverity,
                $details,
            );
        }

        if (in_array($data['channel'], ['opsgenie', 'both'])) {
            $ogPriority = config("incident-alerting.severity_mapping.{$data['severity']}.opsgenie", 'P3');
            $results['opsgenie'] = $this->opsGenie->createAlert(
                $data['summary'],
                $ogPriority,
                $details,
            );
        }

        $allSuccess = collect($results)->every(fn($r) => $r['success']);
        return $allSuccess
            ? ApiResponse::success($results, '告警已推送')
            : ApiResponse::error($results, 400);
    }
}
