<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scim_configs')) {
            return;
        }
        // SCIM 配置表
        Schema::create('scim_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('name')->default('default');
            $table->string('provider', 50)->default('generic')->comment('okta/azure/onelogin/generic');
            $table->boolean('enabled')->default(false);
            $table->string('base_url')->nullable();
            $table->string('api_token', 255)->nullable();
            $table->string('token_type', 30)->default('bearer');
            $table->json('attribute_mapping')->nullable()->comment('IdP属性->系统字段映射');
            $table->json('options')->nullable()->comment('同步选项: auto_create, auto_update, auto_deprovision, role_map');
            $table->string('sync_frequency', 20)->default('manual')->comment('manual/hourly/daily/weekly');
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 30)->nullable()->comment('success/failed/running');
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });

        // SCIM 同步日志表
        Schema::create('scim_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('scim_config_id')->index();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('direction', 10)->default('inbound')->comment('inbound/outbound');
            $table->string('status', 30)->default('pending')->comment('pending/running/completed/failed');
            $table->unsignedInteger('total_processed')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('deactivated_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // SCIM 资源映射记录表（跟踪外部ID←→内部ID）
        Schema::create('scim_resource_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('scim_config_id')->index();
            $table->string('resource_type', 30)->default('User')->comment('User/Group');
            $table->string('external_id')->index()->comment('IdP侧的用户/组ID');
            $table->string('external_user_name')->nullable();
            $table->unsignedInteger('internal_id')->comment('本地用户/角色ID');
            $table->string('status', 20)->default('active')->comment('active/inactive/deleted');
            $table->timestamps();

            $table->unique(['tenant_id', 'scim_config_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scim_resource_mappings');
        Schema::dropIfExists('scim_sync_logs');
        Schema::dropIfExists('scim_configs');
    }
};
