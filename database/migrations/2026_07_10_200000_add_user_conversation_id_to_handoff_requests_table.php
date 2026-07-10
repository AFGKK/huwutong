<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 支持 user-chat 会话转人工客服
     */
    public function up(): void
    {
        if (! Schema::hasTable('handoff_requests') || Schema::hasColumn('handoff_requests', 'user_conversation_id')) {
            return;
        }

        Schema::table('handoff_requests', function (Blueprint $table) {
            $table->foreignId('user_conversation_id')->nullable()->after('live_chat_conversation_id')
                ->constrained('user_conversations')->nullOnDelete();
            $table->index('user_conversation_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('handoff_requests') || ! Schema::hasColumn('handoff_requests', 'user_conversation_id')) {
            return;
        }

        Schema::table('handoff_requests', function (Blueprint $table) {
            $table->dropForeign(['user_conversation_id']);
            $table->dropIndex(['user_conversation_id']);
            $table->dropColumn('user_conversation_id');
        });
    }
};
