<?php

namespace App\Http\Controllers\Api;

use App\Events\CallIncoming;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
use App\Services\WebrtcConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallController extends Controller
{
    // ════════════════════════════════════════════
    // RTC-001~004: 音视频通话基础
    // ════════════════════════════════════════════

    /**
     * 发起呼叫
     */
    public function call(Request $request): JsonResponse
    {
        $request->validate([
            'callee_id' => 'required|integer|exists:users,id',
            'call_type' => 'required|in:audio,video',
            'conversation_id' => 'nullable|integer|exists:user_conversations,id',
        ]);

        $myId = auth()->id();
        $calleeId = (int) $request->input('callee_id');

        if ($myId === $calleeId) {
            return ApiResponse::error('SELF_CALL', __('app.api.call.cannot_self'), 400);
        }

        // 检查是否已有进行中的通话
        $activeCall = \App\Models\CallLog::where(function ($q) use ($myId, $calleeId) {
                $q->where('caller_id', $myId)->where('callee_id', $calleeId);
            })->orWhere(function ($q) use ($myId, $calleeId) {
                $q->where('caller_id', $calleeId)->where('callee_id', $myId);
            })->whereIn('status', ['calling', 'connected'])
            ->first();

        if ($activeCall) {
            return ApiResponse::error('CALL_EXISTS', __('app.api.call.already_in_call'), 409);
        }

        $convId = $request->input('conversation_id');

        // 如果没有指定会话，创建或找到 P2P 会话
        if (!$convId) {
            $conv = UserConversation::where('type', 'private')
                ->whereHas('participants', fn($q) => $q->where('user_id', $myId))
                ->whereHas('participants', fn($q) => $q->where('user_id', $calleeId))
                ->first();
            if ($conv) {
                $convId = $conv->id;
            } else {
                // 创建临时通话会话
                $conv = UserConversation::create([
                    'name' => __('app.api.call.conv_name'),
                    'type' => 'private',
                    'created_by' => $myId,
                ]);
                ConversationParticipant::insert([
                    ['conversation_id' => $conv->id, 'user_id' => $myId],
                    ['conversation_id' => $conv->id, 'user_id' => $calleeId],
                ]);
                $convId = $conv->id;
            }
        }

        $callLog = \App\Models\CallLog::create([
            'caller_id' => $myId,
            'callee_id' => $calleeId,
            'conversation_id' => $convId,
            'call_type' => $request->input('call_type'),
            'status' => 'calling',
            'started_at' => now(),
        ]);

        // 发送呼叫消息到会话
        ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => $myId,
            'content' => $request->input('call_type') === 'video' ? __('app.api.call.start_video') : __('app.api.call.start_voice'),
            'message_type' => 'text',
            'client_msg_id' => 'call-' . uniqid(),
        ]);

        $caller = auth()->user();
        event(new CallIncoming(
            $callLog->id,
            $myId,
            $caller->name ?? __('app.api.call.unknown'),
            $calleeId,
            $callLog->call_type,
            $convId,
        ));

        return ApiResponse::success([
            'call_id' => $callLog->id,
            'conversation_id' => $convId,
            'caller_id' => $myId,
            'callee_id' => $calleeId,
            'call_type' => $callLog->call_type,
            'status' => $callLog->status,
        ], __('app.api.call.initiated'), 201);
    }

    /**
     * 接听/拒接
     */
    public function respond(int $callId, Request $request): JsonResponse
    {
        $request->validate(['action' => 'required|in:accept,reject']);

        $call = \App\Models\CallLog::findOrFail($callId);
        $myId = auth()->id();

        if ($call->callee_id !== $myId) {
            return ApiResponse::error('FORBIDDEN', __('app.api.call.not_callee'), 403);
        }

        if ($call->status !== 'calling') {
            return ApiResponse::error('INVALID_STATUS', __('app.api.call.status_changed'), 400);
        }

        $action = $request->input('action');

        if ($action === 'accept') {
            $call->update(['status' => 'connected']);
            return ApiResponse::success(['call_id' => $callId, 'status' => 'connected'], __('app.api.call.answered'));
        } else {
            $call->update(['status' => 'rejected', 'ended_at' => now()]);
            return ApiResponse::success(['call_id' => $callId, 'status' => 'rejected'], __('app.api.call.rejected'));
        }
    }

    /**
     * 结束通话
     */
    public function end(int $callId): JsonResponse
    {
        $call = \App\Models\CallLog::findOrFail($callId);
        $myId = auth()->id();

        if ($call->caller_id !== $myId && $call->callee_id !== $myId) {
            return ApiResponse::error('FORBIDDEN', __('app.api.call.not_participant'), 403);
        }

        $now = now();
        $duration = $call->started_at ? $now->diffInSeconds($call->started_at) : 0;

        $call->update([
            'status' => 'ended',
            'duration' => $duration,
            'ended_at' => $now,
        ]);

        return ApiResponse::success([
            'call_id' => $callId,
            'duration' => $duration,
            'status' => 'ended',
        ], __('app.api.call.ended'));
    }

    /**
     * 通话状态查询
     */
    public function status(int $callId): JsonResponse
    {
        $call = \App\Models\CallLog::with('caller:id,name', 'callee:id,name')->findOrFail($callId);

        $myId = auth()->id();
        if ($call->caller_id !== $myId && $call->callee_id !== $myId) {
            return ApiResponse::error('FORBIDDEN', __('app.api.call.not_participant'), 403);
        }

        return ApiResponse::success($call);
    }

    /**
     * WebRTC 信令交换
     */
    public function signal(int $callId, Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:offer,answer,ice_candidate',
            'data' => 'required',
        ]);

        $call = \App\Models\CallLog::findOrFail($callId);
        $myId = auth()->id();

        if ($call->caller_id !== $myId && $call->callee_id !== $myId) {
            return ApiResponse::error('FORBIDDEN', __('app.api.call.not_participant'), 403);
        }

        $signalKey = "call_signal_{$callId}_{$request->input('type')}";
        $type = $request->input('type');
        $payload = $request->input('data');

        if ($type === 'ice_candidate') {
            $queue = \Illuminate\Support\Facades\Cache::get($signalKey, []);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[] = $payload;
            \Illuminate\Support\Facades\Cache::put($signalKey, $queue, now()->addMinutes(5));
        } else {
            \Illuminate\Support\Facades\Cache::put($signalKey, $payload, now()->addMinutes(5));
        }

        return ApiResponse::success(null, __('app.api.call.signal_sent'));
    }

    /**
     * 获取信令
     */
    public function signalPoll(int $callId, Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:offer,answer,ice_candidate']);

        $signalKey = "call_signal_{$callId}_{$request->input('type')}";
        $type = $request->input('type');
        $data = \Illuminate\Support\Facades\Cache::get($signalKey);

        if ($type === 'ice_candidate' && is_array($data) && count($data) > 0) {
            $candidate = array_shift($data);
            if (empty($data)) {
                \Illuminate\Support\Facades\Cache::forget($signalKey);
            } else {
                \Illuminate\Support\Facades\Cache::put($signalKey, $data, now()->addMinutes(5));
            }

            return ApiResponse::success(['type' => $type, 'data' => $candidate]);
        }

        if ($data) {
            \Illuminate\Support\Facades\Cache::forget($signalKey);
            return ApiResponse::success(['type' => $type, 'data' => $data]);
        }

        return ApiResponse::success(null, __('app.api.call.no_signal'));
    }

    /**
     * WebRTC ICE 服务器配置（STUN/TURN）
     */
    public function iceServers(WebrtcConfigService $webrtc): JsonResponse
    {
        return ApiResponse::success([
            'ice_servers' => $webrtc->getIceServers(),
            'has_turn' => $webrtc->hasTurn(),
        ]);
    }

    /**
     * 被叫方查询当前来电（轮询降级）
     */
    public function pendingIncoming(): JsonResponse
    {
        $myId = auth()->id();

        $call = \App\Models\CallLog::with('caller:id,name')
            ->where('callee_id', $myId)
            ->where('status', 'calling')
            ->orderByDesc('created_at')
            ->first();

        if (!$call) {
            return ApiResponse::success(null, __('app.api.call.no_incoming'));
        }

        return ApiResponse::success([
            'call_id' => $call->id,
            'caller_id' => $call->caller_id,
            'caller_name' => $call->caller->name ?? __('app.api.call.unknown'),
            'call_type' => $call->call_type,
            'conversation_id' => $call->conversation_id,
            'status' => $call->status,
        ]);
    }

    /**
     * 通话记录
     */
    public function history(Request $request): JsonResponse
    {
        $myId = auth()->id();

        $calls = \App\Models\CallLog::where('caller_id', $myId)
            ->orWhere('callee_id', $myId)
            ->with('caller:id,name', 'callee:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return ApiResponse::paginated($calls);
    }
}
