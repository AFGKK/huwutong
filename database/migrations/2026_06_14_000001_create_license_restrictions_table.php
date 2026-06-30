<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('license_restrictions')) {
            return;
        }
        if (!Schema::hasTable('license_restrictions')) {
            Schema::create('license_restrictions', function (Blueprint $table) {
                $table->id();
                $table->morphs('restrictable'); // license / product
                $table->string('type', 50)->comment('ip_range / geo_fence');
                $table->boolean('is_active')->default(true);
                $table->string('action', 20)->default('block')->comment('block / allow / audit');

                // IP 范围
                $table->json('ip_ranges')->nullable()->comment('["10.0.0.0/8", "192.168.0.0/16"]');
                $table->json('ip_whitelist')->nullable()->comment('["1.2.3.4", "5.6.7.8"]');
                $table->json('ip_blacklist')->nullable();

                // 地理围栏
                $table->json('allowed_countries')->nullable()->comment('ISO codes');
                $table->json('blocked_countries')->nullable();
                $table->string('unknown_location_action', 20)->default('allow');

                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('type');
            });
        }

        if (!Schema::hasTable('license_restriction_logs')) {
            Schema::create('license_restriction_logs', function (Blueprint $table) {
                $table->id();
                $table->morphs('restrictable');
                $table->string('type', 50)->comment('ip_range / geo_fence');
                $table->string('result', 20)->comment('allowed / blocked / audited');
                $table->string('ip_address', 45)->nullable();
                $table->string('country', 5)->nullable();
                $table->string('reason', 255)->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index('created_at');
                $table->index('result');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_restriction_logs');
        Schema::dropIfExists('license_restrictions');
    }
};
