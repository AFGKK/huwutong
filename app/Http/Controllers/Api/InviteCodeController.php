<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InviteChannel;
use App\Models\InviteCode;
use App\Services\InviteCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InviteCodeController extends Controller
{
    public function __construct(
        protected InviteCodeService $inviteCodeService
    ) {}

    // ─── 渠道管理 ───

    public function channels(Request $request)
    {
        return ApiResponse::success(
            $this->inviteCodeService->getChannels($request->only(['type', 'status', 'search', 'per_page']))
        );
    }

    public function showChannel(int $id)
    {
        return ApiResponse::success($this->inviteCodeService->getChannel($id));
    }

    public function storeChannel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:100|unique:invite_channels,slug',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:promotional,marketing,partner,event,social,internal',
            'status' => 'nullable|string|in:active,inactive',
            'tags' => 'nullable|array',
            'is_public' => 'nullable|boolean',
            'max_codes' => 'nullable|integer|min:0',
            'landing_config' => 'nullable|array',
            'utm_defaults' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->inviteCodeService->createChannel($request->all()), 201);
    }

    public function updateChannel(Request $request, InviteChannel $channel)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'slug' => 'nullable|string|max:100|unique:invite_channels,slug,' . $channel->id,
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:promotional,marketing,partner,event,social,internal',
            'status' => 'nullable|string|in:active,inactive',
            'tags' => 'nullable|array',
            'is_public' => 'nullable|boolean',
            'landing_config' => 'nullable|array',
            'utm_defaults' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->inviteCodeService->updateChannel($channel, $request->all()));
    }

    public function destroyChannel(InviteChannel $channel)
    {
        $this->inviteCodeService->deleteChannel($channel);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 渠道统计看板 ───

    public function channelDashboard(int $channelId)
    {
        return ApiResponse::success($this->inviteCodeService->getChannelDashboard($channelId));
    }

    public function overallDashboard()
    {
        return ApiResponse::success($this->inviteCodeService->getOverallDashboard());
    }

    // ─── 邀请码（增强版 CRUD） ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->inviteCodeService->getInviteCodes(
                $request->only(['status', 'channel_id', 'search', 'per_page'])
            )
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'count' => 'required|integer|min:1|max:500',
            'max_uses' => 'nullable|integer|min:1|max:10000',
            'expires_at' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
            'channel_id' => 'nullable|integer|exists:invite_channels,id',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $options = $request->only(['max_uses', 'expires_at', 'remarks', 'channel_id', 'meta']);
        $options['created_by_email'] = $request->user()?->email;

        $codes = $this->inviteCodeService->generateInviteCodes($request->input('count'), $options);

        return ApiResponse::success($codes, 201);
    }

    public function destroy(InviteCode $inviteCode)
    {
        $this->inviteCodeService->disableInviteCode($inviteCode);
        return ApiResponse::success(['disabled' => true]);
    }

    public function stats()
    {
        return ApiResponse::success($this->inviteCodeService->getInviteCodeStats());
    }

    // ─── 注册追踪 ───

    public function registrations(Request $request)
    {
        return ApiResponse::success(
            $this->inviteCodeService->getRegistrationTrackings(
                $request->only(['channel_id', 'source', 'converted', 'date_from', 'date_to', 'per_page'])
            )
        );
    }

    // ─── 自助注册门户 ───

    public function portalConfig()
    {
        return ApiResponse::success($this->inviteCodeService->getPortalConfig());
    }

    public function updatePortalConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'nullable|boolean',
            'title' => 'nullable|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'brand_name' => 'nullable|string|max:200',
            'logo_url' => 'nullable|url|max:500',
            'require_invite' => 'nullable|boolean',
            'require_email_verify' => 'nullable|boolean',
            'accept_terms' => 'nullable|boolean',
            'terms_url' => 'nullable|string|max:500',
            'privacy_url' => 'nullable|string|max:500',
            'allowed_domains' => 'nullable|array',
            'custom_css' => 'nullable|string',
            'custom_html' => 'nullable|string',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->inviteCodeService->updatePortalConfig($request->all()));
    }

    // ─── 验证邀请码（公开端点） ───

    public function validateCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->inviteCodeService->validateInviteCode($request->input('code'))
        );
    }
}
