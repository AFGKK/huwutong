<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('租户名称');
            $table->string('logo')->nullable()->comment('Logo');
            $table->string('domain')->nullable()->unique()->comment('绑定域名');
            $table->string('subscription_plan')->nullable()->comment('订阅套餐');
            $table->string('status')->default('active')->comment('状态: active/inactive/suspended');
            $table->string('data_region')->default('cn')->comment('数据驻留区域');
            $table->json('branding')->nullable()->comment('品牌信息');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
