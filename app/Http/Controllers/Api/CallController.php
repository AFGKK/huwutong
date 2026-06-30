<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
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
            return ApiResponse::error('SELF_CALL', '不能呼叫自己', 400);
        }

        // 检查是否已有进行中的通话
        $activeCall = \App\Models\CallLog::where(function ($q) use ($myId, $calleeId) {
                $q->where('caller_id', $myId)->where('callee_id', $calleeId);
            })->orWhere(function ($q) use ($myId, $calleeId) {
                $q->where('caller_id', $calleeId)->where('callee_id', $myId);
            })->whereIn('status', ['calling', 'connected'])
            ->first();

        if ($activeCall) {
            return ApiResponse::error('CALL_EXISTS', '已有进行中的通话', 409);
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
                    'name' => '通话',
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
            'content' => ($request->input('call_type') === 'video' ? '📹' : '📞') . ' 发起' . ($request->input('call_type') === 'video' ? '视频' : '语音') . '通话',
            'message_type' => 'text',
            'client_msg_id' => 'call-' . uniqid(),
        ]);

        return ApiResponse::success([
            'call_id' => $callLog->id,
            'conversation_id' => $convId,
            'caller_id' => $myId,
            'callee_id' => $calleeId,
            'call_type' => $callLog->call_type,
            'status' => $callLog->status,
        ], '呼叫已发起', 201);
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
            return ApiResponse::error('FORBIDDEN', '你不是被叫方', 403);
        }

        if ($call->status !== 'calling') {
            return ApiResponse::error('INVALID_STATUS', '通话状态已变更', 400);
        }

        $action = $request->input('action');

        if ($action === 'accept') {
            $call->update(['status' => 'connected']);
            return ApiResponse::success(['call_id' => $callId, 'status' => 'connected'], '已接听');
        } else {
            $call->update(['status' => 'rejected', 'ended_at' => now()]);
            return ApiResponse::success(['call_id' => $callId, 'status' => 'rejected'], '已拒绝');
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
            return ApiResponse::error('FORBIDDEN', '你不是通话参与者', 403);
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
        ], '通话已结束');
    }

    /**
     * 通话状态查询
     */
    public function status(int $callId): JsonResponse
    {
        $call = \App\Models\CallLog::with('caller:id,name', 'callee:id,name')->findOrFail($callId);

        $myId = auth()->id();
        if ($call->caller_id !== $myId && $call->callee_id !== $myId) {
            return ApiResponse::error('FORBIDDEN', '你不是通话参与者', 403);
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
            return ApiResponse::error('FORBIDDEN', '你不是通话参与者', 403);
        }

        // 存储信令（使用 Redis/缓存更合适，这里用文件 DB 简化）
        $signalKey = "call_signal_{$callId}_{$request->input('type')}";
        \Illuminate\Support\Facades\Cache::put($signalKey, $request->input('data'), now()->addMinutes(5));

        return ApiResponse::success(null, '信令已发送');
    }

    /**
     * 获取信令
     */
    public function signalPoll(int $callId, Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:offer,answer,ice_candidate']);

        $signalKey = "call_signal_{$callId}_{$request->input('type')}";
        $data = \Illuminate\Support\Facades\Cache::get($signalKey);

        if ($data) {
            \Illuminate\Support\Facades\Cache::forget($signalKey);
            return ApiResponse::success(['type' => $request->input('type'), 'data' => $data]);
        }

        return ApiResponse::success(null, '无新信令');
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
