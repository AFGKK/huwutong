<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversion_funnel_events')) {
            return;
        }
        Schema::create('conversion_funnel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage', 50)->index();
            $table->string('event', 100)->comment('具体事件名');
            $table->json('metadata')->nullable();
            $table->string('source', 50)->nullable()->comment('utm_source/渠道');
            $table->string('campaign', 100)->nullable()->comment('utm_campaign');
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'stage']);
            $table->index(['customer_id', 'stage']);
            $table->index('occurred_at');
        });

        Schema::create('conversion_funnel_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('trial_registered')->default(0);
            $table->unsignedInteger('sdk_downloaded')->default(0);
            $table->unsignedInteger('sdk_activated')->default(0);
            $table->unsignedInteger('first_validation')->default(0);
            $table->unsignedInteger('feature_used')->default(0);
            $table->unsignedInteger('converted')->default(0);
            $table->float('conversion_rate')->default(0);
            $table->json('by_source')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_funnel_summaries');
        Schema::dropIfExists('conversion_funnel_events');
    }
};
