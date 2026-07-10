<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('polls', 'oa_article_id')) { return; }
        Schema::table('polls', function (Blueprint $table) {
            $table->unsignedBigInteger('oa_article_id')->nullable()->after('creator_id');
            $table->foreign('oa_article_id')->references('id')->on('oa_articles')->onDelete('cascade');
            $table->index('oa_article_id');
        });
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropForeign(['oa_article_id']);
            $table->dropColumn('oa_article_id');
        });
    }
};
