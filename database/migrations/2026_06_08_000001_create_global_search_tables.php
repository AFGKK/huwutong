<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 搜索索引表（预索引关键字段，加速跨模块搜索）
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 50)->comment('资源类型: license/customer/product/ticket/invoice/subscription/user/api_key/webhook/log/device');
            $table->unsignedBigInteger('resource_id')->comment('资源主键 ID');
            $table->string('title', 500)->comment('搜索标题');
            $table->text('content')->nullable()->comment('搜索内容（全文）');
            $table->string('status', 50)->nullable()->comment('状态标签');
            $table->string('identifier', 200)->nullable()->comment('标识符如 license_key/email');
            $table->json('tags')->nullable()->comment('标签');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->string('url', 500)->nullable()->comment('详情页 URL');
            $table->unsignedInteger('weight')->default(0)->comment('搜索权重');
            $table->timestamps();

            $table->index(['tenant_id', 'resource_type']);
            $table->index(['tenant_id', 'title']);
            $table->index(['tenant_id', 'identifier']);
            // fullText 全文索引（MySQL only，SQLite 测试环境跳过）
            if (config('database.default') !== 'sqlite' && DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'content'], 'search_ft');
            }
            $table->unique(['tenant_id', 'resource_type', 'resource_id'], 'search_resource_unique');
        });

        // 最近搜索记录
        Schema::create('recent_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 500)->comment('搜索关键词');
            $table->string('resource_type', 50)->nullable()->comment('限定类型');
            $table->json('filters')->nullable()->comment('过滤条件');
            $table->integer('result_count')->default(0)->comment('结果数');
            $table->timestamp('searched_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'searched_at']);
            $table->index(['user_id', 'query']);
        });

        // 搜索收藏
        Schema::create('search_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 50)->comment('资源类型');
            $table->unsignedBigInteger('resource_id')->comment('资源 ID');
            $table->string('label', 200)->nullable()->comment('自定义标签');
            $table->string('notes', 500)->nullable()->comment('备注');
            $table->timestamps();

            $table->unique(['user_id', 'resource_type', 'resource_id'], 'bookmark_unique');
            $table->index(['user_id', 'created_at']);
        });

        // 用户搜索偏好设置
        Schema::create('search_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('recent_types')->nullable()->comment('最近使用的搜索类型');
            $table->json('favorite_types')->nullable()->comment('常用搜索类型');
            $table->json('excluded_types')->nullable()->comment('排除的类型');
            $table->integer('results_per_page')->default(20);
            $table->boolean('show_recent')->default(true);
            $table->boolean('show_suggestions')->default(true);
            $table->boolean('enable_shortcuts')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_preferences');
        Schema::dropIfExists('search_bookmarks');
        Schema::dropIfExists('recent_searches');
        Schema::dropIfExists('search_index');
    }
};
