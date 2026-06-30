<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siem_connections')) {
            return;
        }
        if (!Schema::hasTable('siem_connections')) {
            Schema::create('siem_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->string('name', 100)->comment('连接名称');
                $table->string('format', 20)->default('elk_json')->comment('cef/elk_json/sls');
                $table->string('endpoint_url', 500)->nullable()->comment('推送端点URL');
                $table->string('auth_type', 30)->default('none')->comment('none/bearer_token/basic/api_key');
                $table->text('auth_credentials')->nullable()->comment('加密后的凭证JSON');
                $table->json('field_mappings')->nullable()->comment('自定义字段映射，覆盖默认');
                $table->json('filters')->nullable()->comment('推送筛选条件');
                $table->boolean('is_active')->default(true);
                $table->boolean('auto_push')->default(false)->comment('是否自动推送');
                $table->string('push_frequency', 30)->default('realtime')->comment('realtime/hourly/daily');
                $table->integer('max_batch_size')->default(1000);
                $table->timestamp('last_push_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('siem_push_logs')) {
            Schema::create('siem_push_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('siem_connection_id');
                $table->string('status', 20)->comment('success/failed');
                $table->integer('records_count')->default(0);
                $table->integer('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->text('error_message')->nullable();
                $table->decimal('duration_ms', 10, 2)->default(0);
                $table->timestamps();

                $table->foreign('siem_connection_id')->references('id')->on('siem_connections')->onDelete('cascade');
                $table->index(['siem_connection_id', 'created_at']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siem_push_logs');
        Schema::dropIfExists('siem_connections');
    }
};
