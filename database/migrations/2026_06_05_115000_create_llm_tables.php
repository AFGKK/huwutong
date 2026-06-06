<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LLM Provider 配置表
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('名称');
            $table->string('slug')->unique()->comment('标识: deepseek/openai/claude/tongyi/wenxin/glm');
            $table->string('driver')->comment('驱动类: deepseek/openai/anthropic/tongyi/wenxin/zhipu');
            $table->string('api_base')->nullable()->comment('API 地址');
            $table->text('api_key')->nullable()->comment('API Key（加密存储）');
            $table->json('models')->nullable()->comment('支持的模型列表');
            $table->string('default_model')->nullable()->comment('默认模型');
            $table->json('config')->nullable()->comment('额外配置：超时/最大Token/温度等');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->boolean('is_fallback')->default(false)->comment('是否作为备用');
            $table->timestamps();
        });

        // LLM 对话/请求日志
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('user');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('llm_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model')->comment('使用的模型');
            $table->string('function')->nullable()->comment('功能标识: chat/stream/embedding');
            $table->text('prompt')->nullable()->comment('输入提示词（截断）');
            $table->longText('response')->nullable()->comment('响应内容（截断）');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->float('cost_usd')->default(0)->comment('预估费用 USD');
            $table->integer('duration_ms')->default(0)->comment('请求耗时');
            $table->integer('http_code')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
        Schema::dropIfExists('llm_providers');
    }
};
