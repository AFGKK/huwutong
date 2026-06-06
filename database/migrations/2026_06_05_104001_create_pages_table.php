<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->comment('页面标识: about/privacy/terms/contact');
            $table->string('title')->comment('页面标题');
            $table->text('content')->nullable()->comment('页面内容 (HTML/Markdown)');
            $table->string('locale')->default('zh-CN')->comment('语言');
            $table->string('status')->default('draft')->comment('状态: draft/published');
            $table->json('meta')->nullable()->comment('SEO meta: title/description/keywords');
            $table->unsignedInteger('version')->default(1)->comment('版本号');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
