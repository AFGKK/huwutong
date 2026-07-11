<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallIncoming implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $callId,
        public int $callerId,
        public string $callerName,
        public int $calleeId,
        public string $callType,
        public ?int $conversationId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->calleeId)];
    }

    public function broadcastAs(): string
    {
        return 'call.incoming';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'caller_id' => $this->callerId,
            'caller_name' => $this->callerName,
            'callee_id' => $this->calleeId,
            'call_type' => $this->callType,
            'conversation_id' => $this->conversationId,
        ];
    }
}
