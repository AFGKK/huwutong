<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 统一转接系统：合并 LiveChatHandoff → HandoffRequest
     *
     * - 添加 live_chat_conversation_id 使 HandoffRequest 支持 LiveChat 场景
     * - conversation_id 改为可空（因为 LiveChat 的不用关联 rag_conversations）
     * - 迁移现有 live_chat_handoffs 数据到 handoff_requests
     */
    public function up(): void
    {
        // 1. 修改 handoff_requests 表：支持 live_chat 来源
        Schema::table('handoff_requests', function (Blueprint $table) {
            // conversation_id 改为可空
            $table->foreignId('conversation_id')->nullable()->change();

            // 新增 live_chat_conversation_id
            $table->foreignId('live_chat_conversation_id')->nullable()->after('conversation_id')
                ->constrained('live_chat_conversations')->nullOnDelete();

            // 索引
            $table->index('live_chat_conversation_id');
        });

        // 2. 迁移现有 live_chat_handoffs 到 handoff_requests
        $liveHandoffs = DB::table('live_chat_handoffs')->get();
        foreach ($liveHandoffs as $lh) {
            $existing = DB::table('handoff_requests')
                ->where('live_chat_conversation_id', $lh->conversation_id)
                ->whereIn('status', ['queued', 'assigned', 'in_progress'])
                ->exists();

            if ($existing) {
                continue;
            }

            // 获取 live_chat_conversation 的 tenant_id
            $conv = DB::table('live_chat_conversations')->find($lh->conversation_id);
            $tenantId = $conv->tenant_id ?? 1;

            $statusMap = [
                'pending' => 'queued',
                'accepted' => 'in_progress',
                'rejected' => 'closed',
                'closed' => 'closed',
            ];

            DB::table('handoff_requests')->insert([
                'tenant_id' => $tenantId,
                'conversation_id' => null,
                'live_chat_conversation_id' => $lh->conversation_id,
                'customer_id' => null,
                'user_id' => $conv->user_id ?? null,
                'assigned_to' => $lh->agent_id,
                'ticket_id' => null,
                'reason' => $lh->reason ?? 'user_request',
                'status' => $statusMap[$lh->status] ?? 'closed',
                'priority' => 'medium',
                'queue_position' => null,
                'wait_time_seconds' => null,
                'conversation_context' => json_encode(['migrated_from' => 'live_chat_handoffs', 'original_id' => $lh->id]),
                'metadata' => json_encode(['source' => 'live_chat', 'notes' => $lh->notes]),
                'queued_at' => $lh->handoff_at ?? $lh->created_at,
                'assigned_at' => $lh->status === 'accepted' ? $lh->handoff_at : null,
                'accepted_at' => $lh->status === 'accepted' ? $lh->handoff_at : null,
                'resolved_at' => in_array($lh->status, ['closed']) ? $lh->resolved_at ?? $lh->updated_at : null,
                'closed_at' => in_array($lh->status, ['closed']) ? $lh->updated_at : null,
                'created_at' => $lh->created_at,
                'updated_at' => $lh->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('handoff_requests', function (Blueprint $table) {
            $table->dropForeign(['live_chat_conversation_id']);
            $table->dropIndex(['live_chat_conversation_id']);
            $table->dropColumn('live_chat_conversation_id');
            $table->foreignId('conversation_id')->nullable(false)->change();
        });
    }
};
