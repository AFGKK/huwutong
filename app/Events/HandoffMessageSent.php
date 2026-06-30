<?php

namespace App\Events;

use App\Models\HandoffRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HandoffMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 转接请求
     */
    public HandoffRequest $handoff;

    /**
     * 消息内容
     */
    public string $message;

    /**
     * 发送者类型 (agent / system)
     */
    public string $senderType;

    /**
     * Create a new event instance.
     */
    public function __construct(HandoffRequest $handoff, string $message, string $senderType = 'agent')
    {
        $this->handoff = $handoff;
        $this->message = $message;
        $this->senderType = $senderType;
    }

    /**
     * 广播频道 — 客户私有频道
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('handoff.' . $this->handoff->id),
        ];
    }

    /**
     * 广播事件名称
     */
    public function broadcastAs(): string
    {
        return 'handoff.message';
    }

    /**
     * 发送到前端的数据
     */
    public function broadcastWith(): array
    {
        return [
            'handoff_id' => $this->handoff->id,
            'message' => $this->message,
            'sender_type' => $this->senderType,
            'timestamp' => now()->toIso8601String(),
            'status' => $this->handoff->status,
        ];
    }
}
