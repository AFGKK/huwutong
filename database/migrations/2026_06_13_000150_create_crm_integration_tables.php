<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_connections')) {
            return;
        }
        Schema::create('crm_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->comment('hubspot/salesforce');
            $table->boolean('is_connected')->default(false);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('instance_url', 500)->nullable()->comment('Salesforce instance');
            $table->string('portal_id', 100)->nullable()->comment('HubSpot portal');
            $table->json('config')->nullable()->comment('同步配置');
            $table->string('status', 30)->default('disconnected')->comment('connected/disconnected/error');
            $table->text('last_error')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
        });

        Schema::create('crm_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_connection_id')->constrained('crm_connections')->cascadeOnDelete();
            $table->string('sync_type', 30)->comment('push/pull');
            $table->string('entity_type', 50)->comment('customer/license');
            $table->string('status', 30)->default('pending')->comment('pending/running/success/partial/failed');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('success')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('error_message')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('entity_type', 50)->comment('customer/license');
            $table->unsignedBigInteger('local_id');
            $table->string('remote_id', 200)->comment('CRM侧ID');
            $table->string('remote_url', 500)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('synced')->comment('synced/pending/conflict');
            $table->timestamps();

            $table->unique(['provider', 'entity_type', 'remote_id']);
            $table->index(['tenant_id', 'entity_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_entity_mappings');
        Schema::dropIfExists('crm_sync_logs');
        Schema::dropIfExists('crm_connections');
    }
};
