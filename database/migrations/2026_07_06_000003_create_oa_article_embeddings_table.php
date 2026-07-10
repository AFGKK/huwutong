<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oa_article_embeddings')) { return; }
        Schema::create('oa_article_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id')->unique();
            $table->json('embedding'); // 128维浮点向量
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('oa_articles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_article_embeddings');
    }
};
