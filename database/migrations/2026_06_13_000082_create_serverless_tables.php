<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('serverless_functions')) {
            return;
        }
        Schema::create('serverless_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('function_id', 100)->unique();
            $table->string('runtime', 30)->default('nodejs');
            $table->unsignedSmallInteger('qps_limit')->default(10);
            $table->unsignedBigInteger('monthly_invocation_limit')->default(100000);
            $table->unsignedBigInteger('invocations_used')->default(0);
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->string('status', 30)->default('active');
            $table->json('auth_config')->nullable()->comment('{type:api_key|jwt, allowed_ips, referrers}');
            $table->timestamp('last_invoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('serverless_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serverless_function_id')->constrained('serverless_functions')->cascadeOnDelete();
            $table->string('invocation_id', 64)->unique();
            $table->string('token_id', 64)->nullable()->index();
            $table->string('source_ip', 45)->nullable();
            $table->unsignedSmallInteger('duration_ms')->default(0);
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('status', 20)->default('success')->comment('success/throttled/rejected/error');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['serverless_function_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serverless_invocations');
        Schema::dropIfExists('serverless_functions');
    }
};
