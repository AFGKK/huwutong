<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('region_deployments')) {
            return;
        }
        Schema::create('region_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('region_key', 50)->unique()->comment('us-east/eu-west/ap-southeast');
            $table->string('name', 100);
            $table->string('provider', 30)->default('aws');
            $table->string('api_url', 500);
            $table->string('status', 30)->default('active')->comment('active/degraded/inactive');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('weight')->default(100);
            $table->json('config')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->boolean('is_healthy')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('active_deployment_id')->nullable();
            $table->string('version', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('region_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source_region', 50);
            $table->string('target_region', 50);
            $table->string('data_type', 50)->comment('license/customer/product/audit_log');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('items_synced')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasTable('region_health_logs')) {
            Schema::create('region_health_logs', function (Blueprint $table) {
                $table->id();
                $table->string('region_key', 50);
                $table->boolean('is_healthy');
                $table->unsignedInteger('response_time_ms')->default(0);
                $table->string('checker_region', 50)->comment('从哪个区域检查');
                $table->json('details')->nullable();
                $table->timestamp('checked_at')->useCurrent();
                $table->index(['region_key', 'checked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('region_health_logs');
        Schema::dropIfExists('region_sync_logs');
        Schema::dropIfExists('region_deployments');
    }
};
