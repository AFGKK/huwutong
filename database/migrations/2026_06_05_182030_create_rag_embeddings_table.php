<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 50)->comment('kb_article/api_doc/faq/other');
            $table->unsignedBigInteger('source_id')->nullable()->comment('来源 ID');
            $table->string('title', 300);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->json('embedding')->nullable()->comment('向量嵌入（JSON 数组）');
            $table->string('locale', 10)->default('zh-CN');
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('rag_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200)->nullable();
            $table->string('locale', 10)->default('zh-CN');
            $table->timestamps();
        });

        Schema::create('rag_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('rag_conversations')->cascadeOnDelete();
            $table->string('role', 20)->comment('user/assistant/system');
            $table->longText('content');
            $table->json('source_documents')->nullable()->comment('引用的文档片段');
            $table->float('confidence')->nullable()->comment('置信度');
            $table->integer('token_count')->nullable();
            $table->float('response_time_ms')->nullable();
            $table->boolean('was_helpful')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_messages');
        Schema::dropIfExists('rag_conversations');
        Schema::dropIfExists('rag_documents');
    }
};
