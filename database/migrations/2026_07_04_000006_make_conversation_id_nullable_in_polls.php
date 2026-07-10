<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('polls', 'conversation_id')) { return; }
        Schema::table('polls', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // 谨慎回滚：如果有 oa_article_id 不为 null 且 conversation_id 为 null 的记录会失败
        // 先更新这些记录的 conversation_id
        \App\Models\Poll::whereNull('conversation_id')->whereNotNull('oa_article_id')->each(function ($poll) {
            // 无法自动回滚，需手动处理
        });
    }
};
