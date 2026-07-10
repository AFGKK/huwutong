<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BI 平台连接配置
        if (Schema::hasTable('bi_connections')) { return; }
        Schema::create('bi_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform'); // snowflake | bigquery | tableau | powerbi
            $table->string('status')->default('disconnected'); // disconnected | connected | error
            $table->text('config')->nullable();       // 加密存储的连接配置
            $table->string('last_error')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'platform']);
        });

        // 数据集定义
        if (Schema::hasTable('bi_datasets')) { return; }
        Schema::create('bi_datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bi_connection_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source_table'); // licenses | customers | orders | invoices | subscriptions
            $table->string('sync_frequency')->default('manual'); // manual | hourly | daily | weekly | monthly
            $table->string('status')->default('active');
            $table->json('field_mapping')->nullable();     // 字段映射
            $table->json('filters')->nullable();            // 数据筛选条件
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // 同步历史
        if (Schema::hasTable('bi_sync_logs')) { return; }
        Schema::create('bi_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bi_dataset_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // running | success | partial | failed
            $table->integer('total_records')->default(0);
            $table->integer('synced_records')->default(0);
            $table->string('error_message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_sync_logs');
        Schema::dropIfExists('bi_datasets');
        Schema::dropIfExists('bi_connections');
    }
};
