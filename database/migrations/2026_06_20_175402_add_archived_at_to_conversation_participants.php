<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        Schema::table('conversation_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_participants', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('last_read_at')->comment('归档时间');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_participants') && Schema::hasColumn('conversation_participants', 'archived_at')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
