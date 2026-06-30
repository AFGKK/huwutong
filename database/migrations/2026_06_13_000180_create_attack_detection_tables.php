<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attack_events')) {
            return;
        }
        Schema::create('attack_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attack_type', 50)->comment('brute_force/zero_day/apt_slow/credential_stuffing/side_channel/api_abuse');
            $table->string('severity', 20)->default('warning')->comment('info/warning/critical');
            $table->decimal('confidence', 5, 2)->default(0)->comment('0.00-1.00');
            $table->string('source_ip', 45)->nullable();
            $table->string('target', 500)->nullable()->comment('目标: endpoint/license_key/user_id');
            $table->string('method', 20)->nullable();
            $table->string('path', 500)->nullable();
            $table->text('description');
            $table->json('raw_data')->nullable()->comment('原始请求数据');
            $table->json('context')->nullable()->comment('分析上下文');
            $table->json('ai_analysis')->nullable()->comment('AI分析结果');
            $table->string('status', 30)->default('open')->comment('open/investigating/blocked/resolved/false_positive');
            $table->string('action_taken', 100)->nullable()->comment('blocked_ip/suspended_license/alerted');
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['attack_type', 'severity']);
            $table->index(['source_ip', 'detected_at']);
            $table->index('detected_at');
        });

        Schema::create('attack_ip_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('reason', 500);
            $table->string('attack_type', 50)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->timestamp('blocked_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->boolean('is_permanent')->default(false);
            $table->timestamps();

            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attack_ip_blocks');
        Schema::dropIfExists('attack_events');
    }
};
