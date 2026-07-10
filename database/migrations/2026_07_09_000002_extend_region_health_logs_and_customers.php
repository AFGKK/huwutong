<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('region_health_logs')) {
            Schema::table('region_health_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('region_health_logs', 'region_key')) {
                    $table->string('region_key', 50)->nullable()->after('id');
                    $table->unsignedInteger('response_time_ms')->nullable()->after('latency_ms');
                    $table->string('checker_region', 50)->nullable()->after('response_time_ms');
                    $table->json('details')->nullable()->after('metrics');
                    $table->index(['region_key', 'checked_at']);
                }
            });

            Schema::table('region_health_logs', function (Blueprint $table) {
                if (Schema::hasColumn('region_health_logs', 'data_center_id')) {
                    $table->foreignId('data_center_id')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'region')) {
                    $table->string('region', 50)->nullable()->after('status');
                }
                if (! Schema::hasColumn('customers', 'channel')) {
                    $table->string('channel', 50)->nullable()->after('region');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('region_health_logs')) {
            Schema::table('region_health_logs', function (Blueprint $table) {
                foreach (['region_key', 'response_time_ms', 'checker_region', 'details'] as $column) {
                    if (Schema::hasColumn('region_health_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                foreach (['region', 'channel'] as $column) {
                    if (Schema::hasColumn('customers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
