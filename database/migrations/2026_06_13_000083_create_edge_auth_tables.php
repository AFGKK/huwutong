<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('edge_nodes')) {
            return;
        }
        Schema::create('edge_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('node_id', 100)->unique();
            $table->string('node_type', 30)->default('cloudflare')->comment('cloudflare/akamai/fastly/custom');
            $table->string('region', 100)->nullable()->comment('部署区域');
            $table->string('api_key', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->json('geo_allowed')->nullable()->comment('允许的地区列表');
            $table->json('config')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('node_type');
        });

        Schema::create('ai_token_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('quotable_type', 50)->comment('edge_node/ai_agent/mcp_server');
            $table->unsignedBigInteger('quotable_id');
            $table->string('model', 50)->default('custom');
            $table->unsignedBigInteger('monthly_token_limit')->default(1000000);
            $table->unsignedBigInteger('tokens_used')->default(0);
            $table->string('overage_action', 20)->default('throttle');
            $table->timestamp('quota_reset_at')->nullable();
            $table->timestamps();

            $table->index(['quotable_type', 'quotable_id']);
            $table->index(['tenant_id', 'quota_reset_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_quotas');
        Schema::dropIfExists('edge_nodes');
    }
};
