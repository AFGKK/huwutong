<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 环境配置
        Schema::create('deploy_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('环境名称：production/staging/development');
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->string('base_url')->nullable();
            $table->string('server_type', 50)->default('self-hosted')->comment('self-hosted/cloud/kubernetes');
            $table->json('config')->nullable()->comment('环境特定配置');
            $table->boolean('is_protected')->default(true)->comment('是否受保护(禁止直接部署)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // 发布/版本
        Schema::create('deploy_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('version', 50)->comment('语义版本号，如 2.5.1');
            $table->string('code_name')->nullable()->comment('发布代号');
            $table->text('changelog')->nullable();
            $table->string('git_branch')->nullable();
            $table->string('git_commit_hash', 40)->nullable();
            $table->string('git_commit_message')->nullable();
            $table->string('author', 100)->nullable();
            $table->string('status', 30)->default('pending')->comment('pending/building/built/deployed/rolled_back/failed');
            $table->json('artifacts')->nullable()->comment('构建产物信息');
            $table->json('metadata')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'version']);
        });

        // 部署作业
        Schema::create('deploy_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deploy_environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deploy_release_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('full')->comment('full/backend_only/frontend_only/rollback');
            $table->string('status', 30)->default('pending')
                ->comment('pending/running/success/failed/rolling_back/rolled_back');
            $table->json('steps')->nullable()->comment('部署步骤及状态');
            $table->text('output')->nullable()->comment('部署日志输出');
            $table->text('error_message')->nullable();
            $table->string('triggered_by', 100)->nullable()->comment('触发人');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'deploy_environment_id', 'status']);
            $table->index(['tenant_id', 'deploy_release_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_jobs');
        Schema::dropIfExists('deploy_releases');
        Schema::dropIfExists('deploy_environments');
    }
};
