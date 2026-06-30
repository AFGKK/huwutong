<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PRES-003: 在线状态扩展（IM 核心表可能尚未创建）
        if (Schema::hasTable('user_online_statuses')) {
            Schema::table('user_online_statuses', function (Blueprint $table) {
                if (! Schema::hasColumn('user_online_statuses', 'custom_status')) {
                    $after = Schema::hasColumn('user_online_statuses', 'is_online') ? 'is_online' : 'status';
                    $table->string('custom_status', 100)->nullable()->after($after);
                }
            });
        }

        // MSG-005: 消息状态机
        if (Schema::hasTable('conversation_messages')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('conversation_messages', 'deliver_status')) {
                    $after = Schema::hasColumn('conversation_messages', 'message_type')
                        ? 'message_type'
                        : (Schema::hasColumn('conversation_messages', 'content') ? 'content' : 'metadata');
                    $table->string('deliver_status', 20)->default('sent')->after($after);
                    $table->timestamp('delivered_at')->nullable()->after('deliver_status');
                    $table->timestamp('read_at')->nullable()->after('delivered_at');
                }
            });
        }

        // SEC-006: 隐私设置
        if (! Schema::hasTable('user_privacy_settings')) {
            Schema::create('user_privacy_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('friend_add_policy', 20)->default('everyone');
                $table->boolean('show_online_status')->default(true);
                $table->boolean('show_read_receipt')->default(true);
                $table->boolean('allow_stranger_message')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_privacy_settings');

        if (Schema::hasTable('conversation_messages') && Schema::hasColumn('conversation_messages', 'deliver_status')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->dropColumn(['deliver_status', 'delivered_at', 'read_at']);
            });
        }

        if (Schema::hasTable('user_online_statuses') && Schema::hasColumn('user_online_statuses', 'custom_status')) {
            Schema::table('user_online_statuses', function (Blueprint $table) {
                $table->dropColumn('custom_status');
            });
        }
    }
};
