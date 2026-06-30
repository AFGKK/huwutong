<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('handoff_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('closed_at')->comment('满意度评分 (1-5)');
            $table->string('rating_comment', 500)->nullable()->after('rating')->comment('满意度评价留言');
            $table->timestamp('rated_at')->nullable()->after('rating_comment')->comment('评价时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handoff_requests', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_comment', 'rated_at']);
        });
    }
};
