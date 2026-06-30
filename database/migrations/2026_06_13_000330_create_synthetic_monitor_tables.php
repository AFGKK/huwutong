<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('synthetic_monitor_regions')) {
            return;
        }
        // 1. 拨测区域配置表
        Schema::create('synthetic_monitor_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('区域代码: ap-asia/eu-europe/us-north-america');
            $table->string('name', 100)->comment('区域名称');
            $table->string('name_en', 100)->nullable();
            $table->json('locations')->nullable()->comment('具体位置列表');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. sla_probes 表增加区域支持字段
        Schema::table('sla_probes', function (Blueprint $table) {
            $table->string('region_code', 30)->nullable()->after('tenant_id')
                ->comment('拨测区域: ap-asia/eu-europe/us-north-america');
            $table->string('location', 100)->nullable()->after('region_code')
                ->comment('具体探测点位置');
            $table->string('probe_type', 20)->default('standard')->after('is_active')
                ->comment('standard/synthetic');
        });

        // 3. sla_probe_results 表增加区域字段
        Schema::table('sla_probe_results', function (Blueprint $table) {
            $table->string('region_code', 30)->nullable()->after('sla_probe_id')
                ->index()->comment('所属区域');
            $table->string('location', 100)->nullable()->after('region_code')
                ->comment('探测点位置');
        });
    }

    public function down(): void
    {
        Schema::table('sla_probe_results', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'location']);
        });
        Schema::table('sla_probes', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'location', 'probe_type']);
        });
        Schema::dropIfExists('synthetic_monitor_regions');
    }
};
