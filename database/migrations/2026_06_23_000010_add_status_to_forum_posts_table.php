<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('forum_posts', 'status')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->string('status', 20)->default('published')->after('content')->index();
                $table->timestamp('scheduled_at')->nullable()->after('status')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['status', 'scheduled_at']);
        });
    }
};
