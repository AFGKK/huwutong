<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SEO 元数据表（适用于多态关联：Page, BlogPost, Product, KbArticle 等）
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('meta_title', 160)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('og_title', 160)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('robots', 100)->default('index,follow')->comment('index,follow / noindex,nofollow');
            $table->string('priority', 10)->default('0.5')->comment('Sitemap priority: 0.0-1.0');
            $table->string('change_frequency', 20)->default('monthly')->comment('always/hourly/daily/weekly/monthly/yearly/never');
            $table->json('json_ld')->nullable()->comment('结构化数据 (JSON-LD)');
            $table->timestamps();
        });

        // URL 重定向表
        Schema::create('url_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source_url', 500)->comment('原始URL路径');
            $table->string('target_url', 500)->comment('目标URL');
            $table->unsignedSmallInteger('status_code')->default(301)->comment('301=Moved Permanently, 302=Found, 307=Temporary');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_wildcard')->default(false)->comment('是否通配符匹配');
            $table->text('notes')->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_url'], 'url_redirects_source_idx');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_redirects');
        Schema::dropIfExists('seo_metadata');
    }
};
