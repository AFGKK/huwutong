<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API 网关路由同步记录表
        Schema::create('api_gateway_routes', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_id', 100)->nullable()->comment('网关侧路由ID');
            $table->string('name', 200)->comment('路由名称');
            $table->string('path')->comment('API 路径');
            $table->json('methods')->comment('HTTP 方法');
            $table->json('config')->nullable()->comment('网关特定配置');
            $table->string('status', 20)->default('synced')->comment('synced|pending|failed|unsynced');
            $table->text('error_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['path', 'gateway_id']);
        });

        // API 网关健康检查日志
        Schema::create('api_gateway_health_logs', function (Blueprint $table) {
            $table->id();
            $table->string('engine', 20)->comment('kong|apisix');
            $table->string('status', 20)->comment('healthy|unhealthy|degraded');
            $table->integer('latency_ms')->default(0);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['engine', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_gateway_health_logs');
        Schema::dropIfExists('api_gateway_routes');
    }
};
