<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ConversationMessage $message;
    public array $participantIds;

    public function __construct(ConversationMessage $message, array $participantIds)
    {
        $this->message = $message;
        $this->participantIds = $participantIds;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->participantIds as $userId) {
            $channels[] = new PrivateChannel('chat.' . $userId);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender?->name ?? '',
            'sender_avatar' => $this->message->sender?->avatar_url ?? '',
            'message_type' => $this->message->message_type,
            'content' => $this->message->content,
            'attachments' => $this->message->attachments,
            'reply_to_id' => $this->message->reply_to_id,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
