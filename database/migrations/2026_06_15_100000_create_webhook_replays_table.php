<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_replays')) {
            return;
        }

        Schema::create('webhook_replays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->comment('pending/processing/success/failed');
            $table->unsignedSmallInteger('attempt_count')->default(1);
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->string('triggered_by', 100)->nullable()->comment('manual/auto');
            $table->unsignedBigInteger('replayed_by')->nullable()->comment('user_id');
            $table->timestamp('replayed_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['webhook_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_replays');
    }
};
