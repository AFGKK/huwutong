<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_consumption_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model', 50);
            $table->string('provider', 30);
            $table->string('feature', 50)->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('session_id', 100)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->boolean('cached')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('model');
            $table->index('feature');
            $table->index('created_at');
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('token_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 10)->default('monthly'); // monthly / quarterly / yearly
            $table->decimal('budget_limit', 12, 2);
            $table->decimal('alert_threshold_1', 5, 2)->default(50);
            $table->decimal('alert_threshold_2', 5, 2)->default(80);
            $table->decimal('alert_threshold_3', 5, 2)->default(90);
            $table->boolean('hard_cap')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
        });

        Schema::create('token_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('token_budget_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // threshold_exceeded / hard_cap_reached / budget_exhausted
            $table->decimal('threshold_pct', 5, 2);
            $table->decimal('current_spend', 12, 2);
            $table->decimal('budget_limit', 12, 2);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_alerts');
        Schema::dropIfExists('token_budgets');
        Schema::dropIfExists('token_consumption_records');
    }
};
