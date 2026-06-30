<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_memories')) {
            return;
        }

        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // 记忆标识与内容
            $table->string('key')->index()->comment('记忆键名，如 user_preference/user_fact/conversation_insight');
            $table->text('content')->comment('记忆内容，支持纯文本/Markdown');

            // 分类与来源
            $table->string('type', 30)->default('fact')->index()->comment('类型: preference/fact/context/insight/behavior');
            $table->string('source', 30)->default('ai_extracted')->index()->comment('来源: ai_extracted/manual/system/conversation');

            // 置信度与优先级
            $table->float('confidence', 8, 4)->default(0.8)->comment('AI置信度 0-1');
            $table->unsignedTinyInteger('priority')->default(0)->comment('优先级 0-255，越高越重要');

            // 分类标签（用于分组和检索）
            $table->string('category', 50)->nullable()->index()->comment('分类标签，如 user_preference/business_info/personal_info');
            $table->json('tags')->nullable()->comment('自定义标签数组');

            // 关联（可选：记忆来源的对话或实体）
            $table->nullableMorphs('memorable');

            // 生命周期
            $table->timestamp('expires_at')->nullable()->comment('过期时间，null为永不过期');
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            // 复合索引
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'is_active', 'expires_at'], 'idx_memories_active');
            $table->index(['user_id', 'confidence', 'priority'], 'idx_memories_importance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};
