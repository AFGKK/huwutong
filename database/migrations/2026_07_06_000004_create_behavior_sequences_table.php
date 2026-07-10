<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oa_behavior_sequences')) { return; }
        Schema::create('oa_behavior_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('article_id');
            $table->string('action', 20); // read, like, favorite
            $table->string('session_id', 100);
            $table->unsignedInteger('position'); // 在会话中的位置序号
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'session_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('article_id');
        });

        // 马尔可夫转移概率表
        if (Schema::hasTable('oa_markov_transitions')) { return; }
        Schema::create('oa_markov_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_article_id');
            $table->unsignedBigInteger('to_article_id');
            $table->unsignedInteger('count')->default(0);
            $table->float('probability')->default(0);

            $table->unique(['from_article_id', 'to_article_id'], 'oa_transitions_from_to_unique');
            $table->index('from_article_id');
            $table->index('to_article_id');
        });

        // 二阶马尔可夫转移（考虑前两步）
        if (Schema::hasTable('oa_markov_transitions_v2')) { return; }
        Schema::create('oa_markov_transitions_v2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_article_id_1');
            $table->unsignedBigInteger('from_article_id_2');
            $table->unsignedBigInteger('to_article_id');
            $table->unsignedInteger('count')->default(0);
            $table->float('probability')->default(0);

            $table->unique(['from_article_id_1', 'from_article_id_2', 'to_article_id'], 'oa_transitions_v2_unique');
            $table->index(['from_article_id_1', 'from_article_id_2']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_markov_transitions_v2');
        Schema::dropIfExists('oa_markov_transitions');
        Schema::dropIfExists('oa_behavior_sequences');
    }
};
