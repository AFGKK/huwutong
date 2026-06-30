<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 为 API 文档端点添加多语言翻译支持
        if (Schema::hasTable('api_doc_endpoints') && !Schema::hasColumn('api_doc_endpoints', 'translations')) {
            Schema::table('api_doc_endpoints', function (Blueprint $table) {
                $table->json('translations')->nullable()->after('metadata')
                    ->comment('多语言翻译 {lang: {summary, description, example_request, example_response}}');
            });
        }

        // 为 API 文档标签添加多语言翻译
        if (Schema::hasTable('api_doc_tags') && !Schema::hasColumn('api_doc_tags', 'translations')) {
            Schema::table('api_doc_tags', function (Blueprint $table) {
                $table->json('translations')->nullable()->after('description')
                    ->comment('多语言翻译 {lang: {label, description}}');
            });
        }

        // 为 API Schema 添加多语言翻译
        if (Schema::hasTable('api_doc_schemas') && !Schema::hasColumn('api_doc_schemas', 'translations')) {
            Schema::table('api_doc_schemas', function (Blueprint $table) {
                $table->json('translations')->nullable()->after('example')
                    ->comment('多语言翻译 {lang: {description, properties}}');
            });
        }

        // 为代码片段添加语言+locale唯一翻译
        if (Schema::hasTable('api_doc_code_snippets') && !Schema::hasColumn('api_doc_code_snippets', 'locale')) {
            Schema::table('api_doc_code_snippets', function (Blueprint $table) {
                $table->string('locale', 10)->default('en')->after('language')
                    ->comment('语言区域: en/zh_CN/ja');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('api_doc_endpoints', 'translations')) {
            Schema::table('api_doc_endpoints', function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
        if (Schema::hasColumn('api_doc_tags', 'translations')) {
            Schema::table('api_doc_tags', function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
        if (Schema::hasColumn('api_doc_schemas', 'translations')) {
            Schema::table('api_doc_schemas', function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
        if (Schema::hasColumn('api_doc_code_snippets', 'locale')) {
            Schema::table('api_doc_code_snippets', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
