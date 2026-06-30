<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 导入任务 ───
        Schema::create('import_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200)->comment('导入任务名称');
            $table->string('entity_type', 80)->comment('导入实体类型: licenses/customers/subscriptions/products/tickets');
            $table->string('file_type', 20)->default('csv')->comment('csv/xlsx');
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->unsignedInteger('file_size')->default(0);
            $table->unsignedInteger('total_rows')->default(0)->comment('CSV/Excel 总行数');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->string('status', 30)->default('uploaded')
                ->comment('uploaded/mapping/preview/validated/importing/completed/failed/cancelled');
            $table->json('preview_data')->nullable()->comment('预览数据(前20行)');
            $table->json('validation_errors')->nullable()->comment('验证错误汇总');
            $table->json('import_result')->nullable()->comment('导入结果摘要');
            $table->json('options')->nullable()->comment('导入选项 {skip_errors, update_existing, batch_size, ...}');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['entity_type', 'status']);
        });

        // ─── 字段映射配置 ───
        Schema::create('import_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_task_id')->constrained()->cascadeOnDelete();
            $table->string('source_field', 200)->comment('源文件列名');
            $table->string('target_field', 200)->comment('目标字段/属性');
            $table->string('target_label', 200)->nullable()->comment('目标字段显示名');
            $table->string('default_value')->nullable()->comment('默认值');
            $table->json('transform_rules')->nullable()->comment('转换规则 {type: trim/uppercase/lowercase/date_format/regex, params}');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_identifier')->default(false)->comment('用于匹配已存在记录');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── 导入日志 ───
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number')->comment('CSV/Excel 行号');
            $table->string('level', 20)->default('info')->comment('info/warning/error');
            $table->string('action', 30)->nullable()->comment('created/updated/skipped/failed');
            $table->json('original_data')->nullable()->comment('原始行数据');
            $table->json('processed_data')->nullable()->comment('处理后数据');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['import_task_id', 'level']);
            $table->index(['import_task_id', 'row_number']);
        });

        // ─── 预设映射模板 ───
        Schema::create('import_mapping_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('entity_type', 80)->comment('licenses/customers/subscriptions/products/tickets');
            $table->json('mappings')->comment('字段映射模板');
            $table->json('default_options')->nullable()->comment('默认导入选项');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['entity_type', 'is_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_templates');
        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('import_field_mappings');
        Schema::dropIfExists('import_tasks');
    }
};
