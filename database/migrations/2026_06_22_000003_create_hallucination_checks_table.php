<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hallucination_checks')) {
            Schema::create('hallucination_checks', function (Blueprint $table) {
                $table->id();
                $table->string('source_type', 50)->comment('ai_reply/kb_article/rag_answer');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->text('original_text')->comment('被检查的原文');
                $table->json('claims')->nullable()->comment('提取的事实主张列表');
                $table->json('results')->nullable()->comment('各主张的校验结果');
                $table->decimal('overall_score', 5, 2)->default(0)->comment('总体可信度 0~1');
                $table->string('verdict', 20)->default('unchecked')->comment('可信/pending/unverified/contradicted');
                $table->integer('total_claims')->default(0);
                $table->integer('verified_claims')->default(0);
                $table->integer('unverifiable_claims')->default(0);
                $table->integer('contradicted_claims')->default(0);
                $table->timestamps();

                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hallucination_checks');
    }
};
