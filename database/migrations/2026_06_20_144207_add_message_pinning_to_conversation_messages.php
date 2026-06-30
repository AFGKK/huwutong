<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages') || Schema::hasColumn('conversation_messages', 'is_pinned')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_messages', 'thread_reply_count')) {
                $after = Schema::hasColumn('conversation_messages', 'thread_parent_id')
                    ? 'thread_parent_id'
                    : 'read_at';
                $table->integer('thread_reply_count')->default(0)->after($after);
            }

            $pinAfter = Schema::hasColumn('conversation_messages', 'thread_reply_count')
                ? 'thread_reply_count'
                : (Schema::hasColumn('conversation_messages', 'read_at') ? 'read_at' : 'content');
            $table->boolean('is_pinned')->default(false)->after($pinAfter);
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            $table->foreignId('pinned_by')->nullable()->after('pinned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_messages') || ! Schema::hasColumn('conversation_messages', 'is_pinned')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropForeign(['pinned_by']);
            $table->dropColumn(['is_pinned', 'pinned_at', 'pinned_by']);
        });
    }
};
