<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── License 模板变量定义 ───
        Schema::create('license_template_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_template_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80)->comment('变量名，如 customer_name');
            $table->string('label', 200)->comment('显示名');
            $table->string('variable_type', 30)->default('string')->comment('string/number/date/boolean/select');
            $table->json('options')->nullable()->comment('select 类型的可选值');
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['license_template_id', 'key']);
        });

        // ─── License 模板变量应用映射（变量→license字段） ───
        Schema::create('license_template_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_template_id')->constrained()->cascadeOnDelete();
            $table->string('template_field', 80)->comment('模板变量 key');
            $table->string('license_field', 80)->comment('license 字段或 metadata 路径');
            $table->string('mapping_type', 20)->default('direct')->comment('direct/metadata');
            $table->timestamps();

            $table->unique(['license_template_id', 'template_field'], 'lt_fm_unique');
        });

        // ─── 批量生成任务 ───
        Schema::create('license_batch_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_template_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200)->comment('任务名称');
            $table->unsignedInteger('total_count')->default(0)->comment('计划生成数量');
            $table->unsignedInteger('success_count')->default(0)->comment('成功数量');
            $table->unsignedInteger('failed_count')->default(0)->comment('失败数量');
            $table->string('status', 30)->default('pending')->comment('pending/processing/completed/failed/partial');
            $table->json('variable_values')->nullable()->comment('变量值JSON数组');
            $table->json('override_rules')->nullable()->comment('覆盖规则');
            $table->json('generated_license_ids')->nullable()->comment('已生成的License ID列表');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // ─── 批量生成行记录 ───
        Schema::create('license_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_generation_id')->constrained('license_batch_generations')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('row_index')->comment('行号');
            $table->json('variables')->nullable()->comment('此行变量值');
            $table->text('error_message')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending/success/failed');
            $table->timestamps();

            $table->index(['batch_generation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_batch_items');
        Schema::dropIfExists('license_batch_generations');
        Schema::dropIfExists('license_template_field_mappings');
        Schema::dropIfExists('license_template_variables');
    }
};
