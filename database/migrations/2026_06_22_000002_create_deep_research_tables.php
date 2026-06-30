<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deep_research_tasks')) {
            Schema::create('deep_research_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('query', 500)->comment('研究问题');
                $table->json('sub_questions')->nullable()->comment('LLM 拆解的子问题列表');
                $table->json('findings')->nullable()->comment('各子问题的搜索结果');
                $table->longText('report')->nullable()->comment('最终生成的结构化报告');
                $table->string('status', 20)->default('pending')->comment('pending/in_progress/ completed/failed');
                $table->string('source_count', 20)->default('0')->comment('检索来源数');
                $table->integer('total_tokens')->default(0);
                $table->float('progress')->default(0)->comment('0~100 进度百分比');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('status');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deep_research_tasks');
    }
};
