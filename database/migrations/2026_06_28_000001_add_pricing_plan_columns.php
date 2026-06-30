<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_plans', 'price_monthly')) {
                $table->decimal('price_monthly', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'price_quarterly')) {
                $table->decimal('price_quarterly', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'price_semi_annually')) {
                $table->decimal('price_semi_annually', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'price_yearly')) {
                $table->decimal('price_yearly', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'features')) {
                $table->json('features')->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'limits')) {
                $table->json('limits')->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'trial_days')) {
                $table->integer('trial_days')->default(0);
            }
            if (!Schema::hasColumn('pricing_plans', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
            if (!Schema::hasColumn('pricing_plans', 'is_public')) {
                $table->boolean('is_public')->default(true);
            }
            if (!Schema::hasColumn('pricing_plans', 'badge')) {
                $table->string('badge')->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'metadata')) {
                $table->json('metadata')->nullable();
            }
            if (!Schema::hasColumn('pricing_plans', 'currency')) {
                $table->string('currency', 3)->default('CNY');
            }
            if (!Schema::hasColumn('pricing_plans', 'billing_period')) {
                $table->string('billing_period', 30)->default('monthly');
            }
            if (!Schema::hasColumn('pricing_plans', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn([
                'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_yearly',
                'features', 'limits', 'trial_days', 'sort_order', 'is_public', 'badge', 'metadata',
            ]);
        });
    }
};
