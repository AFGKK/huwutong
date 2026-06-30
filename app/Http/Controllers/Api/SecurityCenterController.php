<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\IpWhitelist;
use App\Models\LoginPolicy;
use App\Models\SecurityEvent;
use App\Models\UserSession;
use App\Services\SecurityCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecurityCenterController extends Controller
{
    public function __construct(
        protected SecurityCenterService $securityService
    ) {}

    // ─── 概览 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->securityService->getDashboard($request->user()->tenant_id)
        );
    }

    public function securityScore(Request $request)
    {
        return ApiResponse::success(
            $this->securityService->getSecurityScore($request->user()->tenant_id)
        );
    }

    // ─── IP 白名单 ───

    public function ipWhitelists(Request $request)
    {
        return ApiResponse::success(
            $this->securityService->getIpWhitelists(
                $request->user()->tenant_id,
                $request->input('type')
            )
        );
    }

    public function storeIpWhitelist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|string|max:45',
            'label' => 'nullable|string|max:200',
            'type' => 'required|string|in:whitelist,blacklist',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->securityService->createIpWhitelist($data), 201);
    }

    public function updateIpWhitelist(Request $request, IpWhitelist $ipWhitelist)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'nullable|string|max:200',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->securityService->updateIpWhitelist($ipWhitelist, $request->all()));
    }

    public function destroyIpWhitelist(IpWhitelist $ipWhitelist)
    {
        $this->securityService->deleteIpWhitelist($ipWhitelist);
        return ApiResponse::success(['deleted' => true]);
    }

    public function bulkImportIps(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ips' => 'required|string',
            'type' => 'required|string|in:whitelist,blacklist',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $ips = explode("\n", str_replace(["\r\n", "\r"], "\n", $request->input('ips')));
        $count = $this->securityService->bulkImportIps(
            $request->user()->tenant_id,
            $ips,
            $request->input('type')
        );

        return ApiResponse::success(['imported' => $count]);
    }

    // ─── 登录策略 ───

    public function policies(Request $request)
    {
        return ApiResponse::success($this->securityService->getPolicies($request->user()->tenant_id));
    }

    public function updatePolicy(Request $request, LoginPolicy $loginPolicy)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'nullable|string',
            'is_enabled' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->securityService->updatePolicy($loginPolicy, $request->all()));
    }

    // ─── 会话管理 ───

    public function sessions(Request $request)
    {
        if ($request->input('all') === 'true') {
            return ApiResponse::success(
                $this->securityService->getActiveSessions($request->user()->tenant_id)
            );
        }

        return ApiResponse::success(
            $this->securityService->getSessions($request->user()->id)
        );
    }

    public function terminateSession(Request $request, UserSession $userSession)
    {
        $this->securityService->terminateSession($userSession->id);
        return ApiResponse::success(['terminated' => true]);
    }

    public function terminateMySessions(Request $request)
    {
        $this->securityService->terminateUserSessions(
            $request->user()->id,
            $request->input('session_id')
        );
        return ApiResponse::success(['terminated' => true]);
    }

    public function terminateAllSessions(Request $request)
    {
        $count = $this->securityService->terminateAllTenantSessions($request->user()->tenant_id);
        return ApiResponse::success(['terminated' => $count]);
    }

    // ─── 安全事件 ───

    public function events(Request $request)
    {
        return ApiResponse::success(
            $this->securityService->getEvents(
                $request->user()->tenant_id,
                $request->only(['event_type', 'severity', 'ip_address', 'user_id', 'date_from', 'date_to']),
                $request->input('page', 1),
                $request->input('per_page', 50)
            )
        );
    }

    public function eventTypes()
    {
        return ApiResponse::success(SecurityEvent::EVENT_TYPES);
    }
}
