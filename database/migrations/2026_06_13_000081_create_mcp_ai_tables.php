<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_servers')) {
            return;
        }
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('server_id', 100)->unique()->comment('MCP Server唯一标识');
            $table->string('protocol', 30)->default('sse')->comment('stdio/sse/websocket');
            $table->string('endpoint', 500)->nullable()->comment('SSE/WS端点');
            $table->json('capabilities')->nullable()->comment('tools/resources/prompts/sampling');
            $table->string('api_key', 100)->nullable()->comment('MCP Server API Key');
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('agent_id', 100)->unique();
            $table->string('framework', 50)->default('custom')->comment('langchain/autogpt/crewai/dify/custom');
            $table->json('capabilities')->nullable();
            $table->string('api_key', 100)->nullable();
            $table->unsignedBigInteger('monthly_token_quota')->default(1000000);
            $table->unsignedBigInteger('tokens_used')->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamp('quota_reset_at')->nullable();
            $table->json('webhook_config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ai_token_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('usage_type', 30)->comment('mcp_server/ai_agent');
            $table->unsignedBigInteger('usage_id');
            $table->string('model', 50)->nullable();
            $table->unsignedBigInteger('tokens')->default(0);
            $table->unsignedBigInteger('requests')->default(0);
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['usage_type', 'usage_id', 'recorded_at']);
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_usage');
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('mcp_servers');
    }
};
