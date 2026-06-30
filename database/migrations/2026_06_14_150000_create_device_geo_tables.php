<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_geo_records')) {
            return;
        }
        // 设备地理位置记录表
        Schema::create('device_geo_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->unsignedInteger('license_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->string('ip_address', 45);
            $table->string('country', 100)->nullable();
            $table->string('country_code', 10)->nullable()->index();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('isp', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('source', 30)->default('activation')->comment('activation/validation/heartbeat');
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'country_code']);
            $table->index(['tenant_id', 'created_at']);
        });

        // 给 devices 表添加 geo 缓存字段
        Schema::table('devices', function (Blueprint $table) {
            $table->string('last_ip', 45)->nullable()->after('platform');
            $table->string('last_country', 100)->nullable()->after('last_ip');
            $table->string('last_country_code', 10)->nullable()->after('last_country');
            $table->string('last_city', 100)->nullable()->after('last_country_code');
            $table->decimal('last_latitude', 10, 7)->nullable()->after('last_city');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
        });

        // 租户级区域统计聚合表
        Schema::create('tenant_geo_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('country_code', 10)->index();
            $table->string('country', 100);
            $table->string('region', 100)->nullable();
            $table->unsignedInteger('device_count')->default(0);
            $table->unsignedInteger('activation_count')->default(0);
            $table->date('stat_date')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'country_code', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['last_ip', 'last_country', 'last_country_code', 'last_city', 'last_latitude', 'last_longitude']);
        });
        Schema::dropIfExists('tenant_geo_stats');
        Schema::dropIfExists('device_geo_records');
    }
};
