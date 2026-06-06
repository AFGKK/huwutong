<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 知识库分类
        Schema::create('kb_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('kb_categories')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->default('zh-CN');
            $table->timestamps();
        });

        // 知识库文章
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 300);
            $table->string('slug', 300)->unique();
            $table->longText('content')->comment('Markdown 内容');
            $table->text('excerpt')->nullable()->comment('摘要');
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('draft')->comment('draft/published/archived');
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('unhelpful_count')->default(0);
            $table->string('locale', 10)->default('zh-CN');
            $table->foreignId('related_article_id')->nullable()->constrained('kb_articles')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->fullText(['title', 'content'], 'kb_articles_fulltext');
        });

        // 文章版本历史
        Schema::create('kb_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('version_number');
            $table->longText('content');
            $table->text('change_summary', 500)->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'version_number']);
        });

        // 文章反馈
        Schema::create('kb_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_feedback');
        Schema::dropIfExists('kb_article_versions');
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('kb_categories');
    }
};
