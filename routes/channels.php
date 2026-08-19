<?php

use App\Models\HandoffRequest;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// 默认用户频道（Laravel Echo 默认）
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// HandoffRequest 对话频道（Live Chat 挂件已退役；HandoffMessageSent 仍广播到此频道）
Broadcast::channel('handoff.{handoffId}', function ($user, $handoffId) {
    $handoff = HandoffRequest::find($handoffId);
    if (! $handoff) {
        return false;
    }
    // 允许请求创建者或其关联客户
    return (int) $user->id === (int) $handoff->user_id
        || (int) $user->id === (int) $handoff->assigned_to;
});

// 租户级通知频道 — 租户内的用户可收听（可选，用于租户广播）
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// 用户 P2P 聊天频道 — 用户只能收听自己的聊天频道
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// SDK 缓存失效推送频道 (M2-134) — SDK 监听此频道接收缓存失效通知
Broadcast::channel('sdk-cache.tenant.{tenantId}', function ($user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});
