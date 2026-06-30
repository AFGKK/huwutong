<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages') || Schema::hasColumn('conversation_messages', 'confirmed_at')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_messages') && Schema::hasColumn('conversation_messages', 'confirmed_at')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->dropColumn('confirmed_at');
            });
        }
    }
};
