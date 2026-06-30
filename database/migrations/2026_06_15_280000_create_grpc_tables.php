<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // gRPC 调用记录表
        Schema::create('grpc_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 50)->index()->comment('服务名: license|device|billing|notification');
            $table->string('method', 100)->comment('方法名');
            $table->string('mode', 20)->comment('grpc|http2|rest');
            $table->string('status', 20)->default('success')->comment('success|error|timeout');
            $table->integer('duration_ms')->default(0)->comment('耗时(毫秒)');
            $table->text('error_message')->nullable();
            $table->boolean('circuit_breaker_open')->default(false);
            $table->timestamps();

            $table->index(['service', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // gRPC 服务注册表（服务发现）
        Schema::create('grpc_service_registry', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 100)->index()->comment('服务名');
            $table->string('host', 100);
            $table->integer('port');
            $table->string('protocol', 20)->default('grpc')->comment('grpc|http2|rest');
            $table->string('status', 20)->default('active')->comment('active|inactive|degraded');
            $table->json('metadata')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->unique(['service_name', 'host', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grpc_service_registry');
        Schema::dropIfExists('grpc_call_logs');
    }
};
