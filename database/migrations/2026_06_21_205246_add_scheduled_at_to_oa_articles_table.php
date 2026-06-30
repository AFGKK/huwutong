<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('published_at')->comment('定时发布时间');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('oa_articles', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
