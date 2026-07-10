<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── CI/CD 集成令牌 ───
        if (Schema::hasTable('ci_cd_tokens')) { return; }
        Schema::create('ci_cd_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->string('description')->nullable();
            // 作用域: license_read | license_write | license_activate | all
            $table->json('scopes');
            // 可访问的 License 范围: null=全部, array=指定ID列表
            $table->json('allowed_license_ids')->nullable();
            $table->string('allowed_ip_range')->nullable();  // CIDR
            $table->unsignedInteger('max_uses')->nullable();  // null=无限制
            $table->unsignedInteger('use_count')->default(0);
            $table->string('status')->default('active');     // active | revoked | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('token');
        });

        // ─── CI/CD 使用日志 ───
        if (Schema::hasTable('ci_cd_usage_logs')) { return; }
        Schema::create('ci_cd_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ci_cd_token_id')->constrained()->cascadeOnDelete();
            $table->string('action');          // license_fetch | license_activate | license_info
            $table->string('ci_provider')->nullable(); // github_actions | gitlab_ci | jenkins | other
            $table->string('repository')->nullable();  // 仓库名
            $table->string('workflow')->nullable();     // workflow/job名
            $table->string('runner_os')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['ci_cd_token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_cd_usage_logs');
        Schema::dropIfExists('ci_cd_tokens');
    }
};
