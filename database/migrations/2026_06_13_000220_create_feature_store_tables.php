<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feature_groups')) {
            return;
        }
        // 特征组
        Schema::create('feature_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('group_key', 100)->unique()->comment('唯一标识');
            $table->string('entity_type', 100)->comment('关联实体类型: customer/license/product');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->comment('active/inactive/deprecated');
            $table->string('source_type', 50)->nullable()->comment('manual/sql_query/api_endpoint/kafka/file_upload/model_output');
            $table->json('source_config')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 特征定义
        Schema::create('feature_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_group_id')->constrained('feature_groups')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('feature_key', 100)->comment('特征标识');
            $table->string('value_type', 30)->comment('int/float/double/string/boolean/json/vector');
            $table->text('description')->nullable();
            $table->boolean('is_online')->default(true)->comment('是否支持在线获取');
            $table->boolean('is_offline')->default(true)->comment('是否支持离线分析');
            $table->string('default_value')->nullable();
            $table->json('validation_rules')->nullable()->comment('验证规则/取值范围');
            $table->json('metadata')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->unique(['feature_group_id', 'feature_key']);
        });

        // 在线特征值
        Schema::create('feature_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_definition_id')->constrained('feature_definitions')->cascadeOnDelete();
            $table->string('entity_id', 100)->comment('实体ID');
            $table->text('value')->nullable();
            $table->string('value_hash', 64)->nullable()->comment('值哈希用于一致性比对');
            $table->timestamp('effective_at')->nullable()->comment('生效时间');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();

            $table->index(['feature_definition_id', 'entity_id']);
            $table->index('expires_at');
        });

        // 离线特征存储（批处理/训练用）
        Schema::create('feature_offline_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_definition_id')->constrained('feature_definitions')->cascadeOnDelete();
            $table->string('entity_id', 100);
            $table->text('value')->nullable();
            $table->string('value_hash', 64)->nullable();
            $table->date('event_date')->comment('分区日期');
            $table->timestamp('batch_processed_at')->nullable();
            $table->timestamps();

            $table->index(['feature_definition_id', 'entity_id', 'event_date']);
            $table->index('event_date');
        });

        // 一致性检查记录
        Schema::create('feature_consistency_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_definition_id')->constrained('feature_definitions')->cascadeOnDelete();
            $table->unsignedInteger('total_samples');
            $table->unsignedInteger('matched_count');
            $table->unsignedInteger('mismatched_count');
            $table->float('match_percent');
            $table->float('drift_percent');
            $table->string('status', 30)->default('passed')->comment('passed/warning/failed');
            $table->json('details')->nullable()->comment('不一致详情');
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();

            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_consistency_checks');
        Schema::dropIfExists('feature_offline_stores');
        Schema::dropIfExists('feature_values');
        Schema::dropIfExists('feature_definitions');
        Schema::dropIfExists('feature_groups');
    }
};
