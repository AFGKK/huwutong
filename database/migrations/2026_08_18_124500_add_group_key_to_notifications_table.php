<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'group_key')) {
                $table->string('group_key', 191)->nullable()->after('type');
                $table->index(['user_id', 'group_key', 'created_at'], 'notifications_user_group_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'group_key')) {
                $table->dropIndex('notifications_user_group_created_idx');
                $table->dropColumn('group_key');
            }
        });
    }
};
