<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('oa_article_reads', 'read_duration')) { return; }
        Schema::table('oa_article_reads', function (Blueprint $table) {
            $table->integer('read_duration')->nullable()->default(0)->after('ip')->comment('阅读时长(秒)');
            $table->integer('scroll_depth')->nullable()->default(0)->after('read_duration')->comment('滚动深度(%)');
            $table->boolean('completed')->nullable()->default(false)->after('scroll_depth')->comment('是否读完');
        });
    }

    public function down(): void
    {
        Schema::table('oa_article_reads', function (Blueprint $table) {
            $table->dropColumn(['read_duration', 'scroll_depth', 'completed']);
        });
    }
};
