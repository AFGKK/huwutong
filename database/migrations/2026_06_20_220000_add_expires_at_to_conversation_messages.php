<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            return;
        }

        if (! Schema::hasColumn('conversation_messages', 'expires_at')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $after = Schema::hasColumn('conversation_messages', 'confirmed_at')
                    ? 'confirmed_at'
                    : (Schema::hasColumn('conversation_messages', 'read_at') ? 'read_at' : 'updated_at');
                $table->timestamp('expires_at')->nullable()->after($after)
                    ->comment('消息自动过期时间，null为永不过期');
            });
        }

        // 索引单独添加，避免 deleted_at 不存在时失败
        $indexes = collect(Schema::getConnection()->select('SHOW INDEX FROM conversation_messages'))
            ->pluck('Key_name')->unique();
        if (! $indexes->contains('idx_expires_cleanup') && Schema::hasColumn('conversation_messages', 'expires_at')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                if (Schema::hasColumn('conversation_messages', 'deleted_at')) {
                    $table->index(['expires_at', 'deleted_at'], 'idx_expires_cleanup');
                } else {
                    $table->index(['expires_at'], 'idx_expires_cleanup');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_messages', 'expires_at')) {
                try {
                    $table->dropIndex('idx_expires_cleanup');
                } catch (\Throwable) {
                }
                $table->dropColumn('expires_at');
            }
        });
    }
};
