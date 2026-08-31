<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 通知实例
     */
    public Notification $notification;

    /**
     * 接收通知的用户 ID
     */
    public int $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification, int $userId)
    {
        $this->notification = $notification;
        $this->userId = $userId;
    }

    /**
     * 广播频道 — 用户私有频道
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    /**
     * 广播事件名称
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * 发送到前端的数据
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'group_key' => $this->notification->group_key,
            'title' => $this->notification->title,
            'content' => $this->notification->content,
            'payload' => $this->notification->payload,
            'is_read' => false,
            'created_at' => $this->notification->created_at?->toIso8601String(),
            'updated_at' => $this->notification->updated_at?->toIso8601String(),
        ];
    }
}
