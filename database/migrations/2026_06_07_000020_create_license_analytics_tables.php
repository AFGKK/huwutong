<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // License 分析事件表 — 记录激活/去激活/心跳/违规等事件用于趋势分析
        Schema::create('license_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type'); // activation, deactivation, heartbeat, violation, checkin, feature_check
            $table->string('ip_address', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('platform')->nullable();      // windows, macos, linux, ios, android
            $table->string('sdk_version')->nullable();
            $table->string('sdk_language')->nullable();
            $table->string('sdk_arch')->nullable();
            $table->string('violation_type')->nullable(); // excessive_activations, expired_use, tampered, blacklisted_device
            $table->text('violation_detail')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['license_id', 'event_type']);
            $table->index(['country_code']);
        });

        // IP 地理位置缓存表 — 避免频繁调用外部 API
        Schema::create('geo_lookups', function (Blueprint $table) {
            $table->string('ip_address', 45)->primary();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('isp')->nullable();
            $table->string('source')->default('cache'); // cache, api
            $table->timestamp('cached_at');
            $table->timestamps();

            $table->index(['country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_analytics_events');
        Schema::dropIfExists('geo_lookups');
    }
};
