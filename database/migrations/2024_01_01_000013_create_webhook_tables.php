<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Webhook Endpoints
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->string('url');
            $table->string('secret', 64)->nullable();
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
        });

        // Webhook Events
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('webhook_endpoint_id')->index();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending, retrying, delivered, paused, dead_letter
            $table->integer('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'webhook_events_retry_idx');
        });

        // Event Deliveries
        Schema::create('event_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_event_id')->index();
            $table->string('url');
            $table->integer('attempt');
            $table->string('status', 20); // success, failed
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoints');
    }
};
