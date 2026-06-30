<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRCH-001: 全文搜索索引（SQLite 不支持 FULLTEXT）
        if (Schema::hasTable('conversation_messages') && Schema::getConnection()->getDriverName() !== 'sqlite') {
            $indexExists = collect(DB::select("SHOW INDEX FROM conversation_messages WHERE Key_name = 'msg_content_ft'"))->isNotEmpty();
            if (! $indexExists) {
                DB::statement('ALTER TABLE conversation_messages ADD FULLTEXT INDEX msg_content_ft (content)');
            }
        }

        // SYNC-004: 消息漫游 - 添加最后同步时间
        if (Schema::hasTable('user_online_statuses')) {
            Schema::table('user_online_statuses', function (Blueprint $table) {
                if (! Schema::hasColumn('user_online_statuses', 'last_sync_at')) {
                    $table->timestamp('last_sync_at')->nullable()->after('last_seen_at');
                }
            });
        }

        // 消息漫游配置表
        if (! Schema::hasTable('message_sync_logs') && Schema::hasTable('user_conversations')) {
            Schema::create('message_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('user_conversations')->cascadeOnDelete();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'conversation_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_sync_logs');

        if (Schema::hasTable('user_online_statuses') && Schema::hasColumn('user_online_statuses', 'last_sync_at')) {
            Schema::table('user_online_statuses', function (Blueprint $table) {
                $table->dropColumn('last_sync_at');
            });
        }

        if (Schema::hasTable('conversation_messages') && Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE conversation_messages DROP INDEX msg_content_ft');
        }
    }
};
