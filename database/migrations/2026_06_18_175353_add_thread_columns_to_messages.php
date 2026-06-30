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

        Schema::table('conversation_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_messages', 'thread_parent_id')) {
                $table->unsignedBigInteger('thread_parent_id')->nullable()->after('id');
                $table->index('thread_parent_id');
            }
            if (! Schema::hasColumn('conversation_messages', 'thread_reply_count')) {
                $after = Schema::hasColumn('conversation_messages', 'thread_parent_id')
                    ? 'thread_parent_id'
                    : 'id';
                $table->integer('thread_reply_count')->default(0)->after($after);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_messages', 'thread_parent_id')) {
                try {
                    $table->dropIndex(['thread_parent_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('thread_parent_id');
            }
            if (Schema::hasColumn('conversation_messages', 'thread_reply_count')) {
                $table->dropColumn('thread_reply_count');
            }
        });
    }
};
