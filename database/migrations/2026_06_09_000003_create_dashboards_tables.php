<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 仪表盘定义 ───
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('layout_type', 30)->default('grid')->comment('grid/free/flex');
            $table->json('layout_config')->nullable()->comment('布局配置 {columns, gap, padding}');
            $table->unsignedSmallInteger('columns')->default(12)->comment('网格列数');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
            $table->index('is_default');
        });

        // ─── 仪表盘 Widget ───
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->comment('widget 类型: stat/chart/list/metric/table/iframe/html/alert/report');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('config')->nullable()->comment('Widget 配置 {dataSource, metric, chartType, ...}');
            $table->json('layout')->nullable()->comment('位置 {x, y, w, h}');
            $table->json('data_source')->nullable()->comment('数据源配置 {type, endpoint, params}');
            $table->json('visual_options')->nullable()->comment('视觉选项 {color, border, refreshInterval}');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['dashboard_id', 'type']);
        });

        // ─── Widget 数据缓存 ───
        Schema::create('dashboard_widget_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->json('data')->nullable()->comment('缓存数据');
            $table->unsignedInteger('refresh_interval_seconds')->default(300);
            $table->timestamp('cached_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });

        // ─── 预置 Widget 模板 ───
        Schema::create('dashboard_widget_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general')->comment('general/license/billing/customer/security/system');
            $table->json('default_config')->nullable();
            $table->json('default_visual_options')->nullable();
            $table->json('default_layout')->nullable()->comment('默认尺寸 {w, h}');
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_templates');
        Schema::dropIfExists('dashboard_widget_caches');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboards');
    }
};
