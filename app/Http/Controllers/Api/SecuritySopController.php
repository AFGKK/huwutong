<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\SecuritySopTemplate;
use App\Services\SecurityCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecuritySopController extends Controller
{
    public function __construct(
        protected SecurityCenterService $securityService,
    ) {}

    // ─── SOP 模板管理 ───

    public function sopTemplates(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->securityService->getSopTemplates(
                $request->only(['status', 'severity', 'name']),
                (int) $request->get('per_page', 20)
            )
        );
    }

    public function storeSopTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'severity' => 'nullable|in:info,warning,critical',
            'trigger_conditions' => 'nullable|array',
            'steps' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,draft',
            'is_auto_execute' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['created_by'] = $request->user()->id;
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']) . '-' . strtolower(\Illuminate\Support\Str::random(6));

        $template = $this->securityService->createSopTemplate($data);

        return ApiResponse::success($template, __("app.security_sop.msg_136ca8b9"), 201);
    }

    public function showSopTemplate(SecuritySopTemplate $securitySopTemplate): JsonResponse
    {
        $securitySopTemplate->load('creator:id,name', 'executions');
        return ApiResponse::success($securitySopTemplate);
    }

    public function updateSopTemplate(Request $request, SecuritySopTemplate $securitySopTemplate): JsonResponse
    {
        $data = $request->validate([
            'name' => 'string|max:200',
            'description' => 'nullable|string',
            'severity' => 'nullable|in:info,warning,critical',
            'trigger_conditions' => 'nullable|array',
            'steps' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,draft',
            'is_auto_execute' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $template = $this->securityService->updateSopTemplate($securitySopTemplate, $data);
        return ApiResponse::success($template, __("app.security_sop.msg_8552a51e"));
    }

    public function deleteSopTemplate(SecuritySopTemplate $securitySopTemplate): JsonResponse
    {
        $securitySopTemplate->delete();
        return ApiResponse::success(null, __("app.security_sop.msg_1901dde5"));
    }

    // ─── SOP 执行 ───

    public function executeSop(Request $request, SecuritySopTemplate $securitySopTemplate): JsonResponse
    {
        $eventId = $request->input('event_id');
        $event = $eventId ? SecurityEvent::find($eventId) : null;

        $execution = $this->securityService->executeSopManually($securitySopTemplate, $event, $request->user()->id);

        return ApiResponse::success($execution, __("app.security_sop.msg_1874549f"));
    }

    public function handleEvent(Request $request, SecurityEvent $securityEvent): JsonResponse
    {
        $execution = $this->securityService->handleSecurityEvent($securityEvent);

        if (!$execution) {
            return ApiResponse::success(null, __("app.security_sop.msg_b0525c18"));
        }

        return ApiResponse::success($execution, __("app.security_sop.msg_96d440b7"));
    }

    public function resolveEvent(Request $request, SecurityEvent $securityEvent): JsonResponse
    {
        $data = $request->validate([
            'resolution' => 'required|in:resolved,false_positive,in_progress',
            'notes' => 'nullable|string|max:2000',
        ]);

        $event = $this->securityService->resolveEvent(
            $securityEvent,
            $data['resolution'],
            $data['notes'] ?? null,
            $request->user()->id,
        );

        return ApiResponse::success($event, __("app.security_sop.msg_6482c88b"));
    }

    // ─── 执行记录 ───

    public function sopExecutions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->securityService->getSopExecutions(
                $request->only(['status', 'sop_template_id']),
                (int) $request->get('per_page', 20)
            )
        );
    }

    // ─── 统计 ───

    public function sopStats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->securityService->getSopStats($request->user()->tenant_id)
        );
    }
}
