<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regional_compliance_configs')) {
            Schema::create('regional_compliance_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('region_key', 30)->comment('cn/eu/us/ap-southeast');
                $table->string('region_name', 100)->nullable();
                $table->boolean('gdpr_enabled')->default(false);
                $table->boolean('pipl_enabled')->default(false);
                $table->boolean('vat_enabled')->default(false);
                $table->boolean('data_residency_enabled')->default(false);
                $table->boolean('cookie_consent_enabled')->default(true);
                $table->boolean('tax_reporting_enabled')->default(true);
                $table->string('tax_type', 30)->nullable()->comment('vat/sales_tax/gst');
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->string('tax_reporting_frequency', 20)->default('quarterly')->comment('monthly/quarterly/yearly');
                $table->boolean('digital_service_tax')->default(false);
                $table->boolean('oss_enabled')->default(false);
                $table->decimal('oss_threshold', 12, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('timezone', 50)->nullable();
                $table->json('languages')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('options')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'region_key']);
            });
        }

        if (! Schema::hasTable('regional_sales_restrictions')) {
            Schema::create('regional_sales_restrictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('restrictable_type');
                $table->unsignedBigInteger('restrictable_id');
                $table->string('region_key', 30)->comment('cn/eu/us/ap-southeast');
                $table->boolean('is_allowed')->default(true);
                $table->string('restriction_type', 30)->default('region')->comment('region/industry/country');
                $table->string('restriction_value', 100)->nullable();
                $table->text('reason')->nullable();
                $table->string('override_by', 50)->nullable();
                $table->timestamp('effective_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['restrictable_type', 'restrictable_id'], 'rsr_morph_idx');
                $table->index(['restrictable_type', 'restrictable_id', 'region_key'], 'rsr_morph_region_idx');
                $table->index('region_key', 'rsr_region_idx');
            });
        }

        if (! Schema::hasTable('regional_compliance_logs')) {
            Schema::create('regional_compliance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('region_key', 30);
                $table->string('action_type', 50)->comment('report_generated/tax_filed/config_updated/sales_blocked/sales_unblocked');
                $table->string('status', 30)->default('success')->comment('success/failed/pending');
                $table->text('description')->nullable();
                $table->json('details')->nullable();
                $table->string('performed_by', 100)->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamps();

                $table->index(['tenant_id', 'region_key', 'action_type'], 'rcl_tenant_region_action_idx');
                $table->index('occurred_at', 'rcl_occurred_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_compliance_logs');
        Schema::dropIfExists('regional_sales_restrictions');
        Schema::dropIfExists('regional_compliance_configs');
    }
};
