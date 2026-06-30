<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API 文档端点定义
        Schema::create('api_doc_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_version_id')->nullable()->constrained('api_versions')->nullOnDelete();
            $table->string('method', 10)->comment('GET/POST/PUT/DELETE/PATCH');
            $table->string('path', 500)->comment('接口路径');
            $table->string('summary', 500)->nullable()->comment('简要说明');
            $table->text('description')->nullable()->comment('详细说明');
            $table->string('group', 100)->nullable()->comment('分组');
            $table->string('tag', 100)->nullable()->comment('标签');
            $table->json('parameters')->nullable()->comment('请求参数');
            $table->json('request_body')->nullable()->comment('请求体 JSON Schema');
            $table->json('responses')->nullable()->comment('响应定义');
            $table->json('headers')->nullable()->comment('请求头');
            $table->json('security')->nullable()->comment('安全认证');
            $table->json('example_request')->nullable()->comment('请求示例');
            $table->json('example_response')->nullable()->comment('响应示例');
            $table->json('code_examples')->nullable()->comment('代码示例');
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('active')->comment('active/deprecated/beta/experimental');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['api_version_id', 'method', 'status']);
            $table->index(['group', 'tag']);
            $table->unique(['api_version_id', 'method', 'path']);
        });

        // API 标签/分组
        Schema::create('api_doc_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // API Schema 注册表
        Schema::create('api_doc_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique()->comment('模式名称');
            $table->string('type', 30)->default('object');
            $table->text('description')->nullable();
            $table->json('schema')->comment('JSON Schema');
            $table->json('example')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        // 代码片段
        Schema::create('api_doc_code_snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('api_doc_endpoints')->cascadeOnDelete();
            $table->string('language', 30);
            $table->string('title', 200)->nullable();
            $table->text('code');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['endpoint_id', 'language']);
        });

        // API 测试请求记录
        Schema::create('api_test_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endpoint_id')->nullable()->constrained('api_doc_endpoints')->nullOnDelete();
            $table->string('method', 10);
            $table->string('url', 1000);
            $table->json('headers')->nullable();
            $table->json('body')->nullable();
            $table->json('response')->nullable();
            $table->integer('response_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // SDK 构建配置
        Schema::create('api_sdk_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('language', 30)->unique();
            $table->string('version', 30)->default('1.0.0');
            $table->text('description')->nullable();
            $table->json('config')->nullable();
            $table->text('install_command')->nullable();
            $table->text('setup_code')->nullable();
            $table->text('readme')->nullable();
            $table->string('download_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // API 变更日志
        Schema::create('api_changelogs', function (Blueprint $table) {
            $table->id();
            $table->string('version', 30);
            $table->date('release_date');
            $table->string('type', 30)->default('update');
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->json('affected_endpoints')->nullable();
            $table->string('migration_guide', 1000)->nullable();
            $table->timestamps();

            $table->index(['version', 'type']);
            $table->index('release_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_changelogs');
        Schema::dropIfExists('api_sdk_configs');
        Schema::dropIfExists('api_test_requests');
        Schema::dropIfExists('api_doc_code_snippets');
        Schema::dropIfExists('api_doc_schemas');
        Schema::dropIfExists('api_doc_tags');
        Schema::dropIfExists('api_doc_endpoints');
    }
};
