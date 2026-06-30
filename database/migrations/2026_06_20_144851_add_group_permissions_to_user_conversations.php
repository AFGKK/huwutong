<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_conversations') || Schema::hasColumn('user_conversations', 'permissions')) {
            return;
        }

        Schema::table('user_conversations', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('join_approval');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('user_conversations') && Schema::hasColumn('user_conversations', 'permissions')) {
            Schema::table('user_conversations', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }
    }
};
