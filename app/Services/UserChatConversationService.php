<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\CardConversionTracking;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Product;
use App\Models\UserConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserChatConversationService
{
    public function findOrCreatePrivateConversation(int $userIdA, int $userIdB): UserConversation
    {
        $ids = [$userIdA, $userIdB];
        sort($ids);

        $existing = DB::table('conversation_participants AS cp1')
            ->join('conversation_participants AS cp2', 'cp1.conversation_id', '=', 'cp2.conversation_id')
            ->join('user_conversations', 'cp1.conversation_id', '=', 'user_conversations.id')
            ->where('cp1.user_id', $ids[0])
            ->where('cp2.user_id', $ids[1])
            ->whereNull('cp1.deleted_at')
            ->whereNull('cp2.deleted_at')
            ->where('user_conversations.type', 'private')
            ->select('user_conversations.id')
            ->first();

        if ($existing) {
            return UserConversation::findOrFail($existing->id);
        }

        $conv = UserConversation::create([
            'type' => 'private',
            'created_by' => $userIdA,
        ]);

        foreach ($ids as $uid) {
            ConversationParticipant::create([
                'conversation_id' => $conv->id,
                'user_id' => $uid,
                'role' => 'member',
            ]);
        }

        return $conv;
    }

    public function pushTextMessage(
        UserConversation $conv,
        int $senderId,
        string $content,
        array $metadata = [],
        ?string $clientMsgId = null
    ): ConversationMessage {
        if ($clientMsgId) {
            $existing = ConversationMessage::where('client_msg_id', $clientMsgId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $senderId,
            'message_type' => 'text',
            'content' => $content,
            'metadata' => $metadata ?: null,
            'client_msg_id' => $clientMsgId,
        ]);

        $conv->update([
            'last_message_id' => $msg->id,
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);

        $participantIds = ConversationParticipant::where('conversation_id', $conv->id)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', '!=', $senderId)
            ->whereNull('deleted_at')
            ->increment('unread_count');

        try {
            event(new ChatMessageSent($msg->load('sender:id,name,avatar'), $participantIds));
        } catch (\Throwable $e) {
            Log::warning('ChatMessageSent broadcast failed: ' . $e->getMessage(), [
                'conversation_id' => $conv->id,
                'message_id' => $msg->id,
            ]);
        }

        return $msg;
    }

    public function pushSystemMessage(
        UserConversation $conv,
        string $content,
        int $senderId,
        array $metadata = []
    ): ConversationMessage {
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $senderId,
            'message_type' => 'system',
            'content' => $content,
            'metadata' => $metadata ?: null,
        ]);

        $conv->update([
            'last_message_id' => $msg->id,
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);

        $participantIds = ConversationParticipant::where('conversation_id', $conv->id)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', '!=', $senderId)
            ->whereNull('deleted_at')
            ->increment('unread_count');

        try {
            event(new ChatMessageSent($msg->load('sender:id,name,avatar'), $participantIds));
        } catch (\Throwable $e) {
            Log::warning('ChatMessageSent broadcast failed: ' . $e->getMessage(), [
                'conversation_id' => $conv->id,
                'message_id' => $msg->id,
            ]);
        }

        return $msg;
    }

    public function pushProductCard(
        UserConversation $conv,
        int $senderId,
        Product $product,
        ?string $content = null,
        ?string $traceId = null,
        array $extraMetadata = []
    ): ConversationMessage {
        $traceId = $traceId ?? ('card-' . uniqid());
        $actionUrl = $product->slug
            ? '/products/' . $product->slug
            : '/products/' . $product->id;

        $cardData = array_merge([
            'type' => 'product_card',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => mb_substr($product->description ?? '', 0, 200),
                'price' => $product->base_price,
                'image_url' => $product->image_url,
                'deep_link' => "im://product?id={$product->id}",
                'action_url' => $actionUrl,
                'action_label' => '查看详情',
            ],
        ], $extraMetadata);

        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $senderId,
            'content' => $content ?? "推荐产品：{$product->name}",
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        $conv->update([
            'last_message_id' => $msg->id,
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);

        $participantIds = ConversationParticipant::where('conversation_id', $conv->id)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', '!=', $senderId)
            ->whereNull('deleted_at')
            ->increment('unread_count');

        try {
            event(new ChatMessageSent($msg->load('sender:id,name,avatar'), $participantIds));
        } catch (\Throwable $e) {
            Log::warning('ChatMessageSent broadcast failed: ' . $e->getMessage(), [
                'conversation_id' => $conv->id,
                'message_id' => $msg->id,
            ]);
        }

        try {
            CardConversionTracking::create([
                'trace_id' => $traceId,
                'card_type' => 'product_card',
                'message_id' => $msg->id,
                'sender_id' => $senderId,
                'event' => 'send',
            ]);
        } catch (\Throwable $e) {
            Log::warning('CardConversionTracking failed: ' . $e->getMessage());
        }

        return $msg;
    }
}
