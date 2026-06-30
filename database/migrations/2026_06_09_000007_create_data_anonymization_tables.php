<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 数据匿名化规则表
        Schema::create('data_anonymization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 100);
            $table->string('field_name', 100);
            $table->string('method', 50)->comment('匿名化方法: chinese_name/email/phone/address/sentence/fixed_value 等');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['table_name', 'field_name']);
            $table->index('table_name');
        });

        // 数据导出任务表
        Schema::create('data_export_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('export')->comment('export/anonymize');
            $table->string('status', 20)->default('pending')->comment('pending/running/completed/failed');
            $table->string('source_connection', 50)->nullable()->comment('源数据库连接名');
            $table->string('target_connection', 50)->nullable()->comment('目标数据库连接名');
            $table->json('tables')->nullable()->comment('要处理的表列表');
            $table->bigInteger('total_records')->default(0);
            $table->bigInteger('processed_records')->default(0);
            $table->json('anonymized_tables')->nullable()->comment('已匿名化的表');
            $table->json('excluded_tables')->nullable()->comment('排除的表');
            $table->string('output_file')->nullable()->comment('导出文件路径');
            $table->bigInteger('file_size')->default(0)->comment('导出文件大小(字节)');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_tasks');
        Schema::dropIfExists('data_anonymization_rules');
    }
};
