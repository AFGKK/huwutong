<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_app_rollouts')) {
            Schema::create('marketplace_app_rollouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('marketplace_apps')->cascadeOnDelete();
                $table->foreignId('version_id')->constrained('marketplace_app_versions')->cascadeOnDelete();
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('rollout_type', 30)->default('percentage')->comment('percentage|tenant_group|user_segment');
                $table->unsignedTinyInteger('percentage')->default(10)->comment('0-100');
                $table->text('target_filters')->nullable()->comment('JSON: tenant_ids/user_segment/region filters');
                $table->string('status', 30)->default('draft')->comment('draft|active|paused|completed|rolled_back');
                $table->boolean('auto_rollback')->default(false);
                $table->decimal('error_threshold', 5, 2)->default(5.00)->comment('error rate % triggers auto rollback');
                $table->unsignedInteger('assigned_count')->default(0);
                $table->unsignedInteger('installed_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['app_id', 'status']);
                $table->index('status');
            });
        }

        if (!Schema::hasTable('marketplace_app_rollout_tenants')) {
            Schema::create('marketplace_app_rollout_tenants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rollout_id')->constrained('marketplace_app_rollouts')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->boolean('included')->default(true)->comment('true=included in rollout, false=excluded');
                $table->timestamps();

                $table->unique(['rollout_id', 'tenant_id']);
            });
        }

        if (!Schema::hasTable('marketplace_app_rollout_events')) {
            Schema::create('marketplace_app_rollout_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rollout_id')->constrained('marketplace_app_rollouts')->cascadeOnDelete();
                $table->foreignId('installation_id')->nullable()->constrained('marketplace_app_installations')->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event_type', 40)->comment('assigned|installed|error|rollback|completed|paused');
                $table->text('message')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();

                $table->index(['rollout_id', 'event_type']);
                $table->index(['rollout_id', 'created_at']);
            });
        }

        // Add rollout/version tracking columns to marketplace_app_installations
        if (Schema::hasTable('marketplace_app_installations')) {
            Schema::table('marketplace_app_installations', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_app_installations', 'rollout_id')) {
                    $table->foreignId('rollout_id')->nullable()->after('installed_version')
                          ->constrained('marketplace_app_rollouts')->nullOnDelete();
                }
                if (!Schema::hasColumn('marketplace_app_installations', 'previous_version')) {
                    $table->string('previous_version', 30)->nullable()->after('rollout_id');
                }
                if (!Schema::hasColumn('marketplace_app_installations', 'auto_updated')) {
                    $table->boolean('auto_updated')->default(false)->after('previous_version');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_app_installations')) {
            Schema::table('marketplace_app_installations', function (Blueprint $table) {
                $table->dropColumn(['rollout_id', 'previous_version', 'auto_updated']);
            });
        }
        Schema::dropIfExists('marketplace_app_rollout_events');
        Schema::dropIfExists('marketplace_app_rollout_tenants');
        Schema::dropIfExists('marketplace_app_rollouts');
    }
};
