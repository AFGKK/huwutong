<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('honeypot_licenses')) {
            return;
        }
        Schema::create('honeypot_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 64)->unique();
            $table->string('label', 100)->nullable()->comment('蜜罐标签，便于识别用途');
            $table->string('status', 20)->default('active')->comment('active=等待触发, triggered=已触发, disabled=已禁用');
            $table->text('notes')->nullable();
            $table->timestamp('triggered_at')->nullable()->comment('首次触发时间');
            $table->string('triggered_ip', 45)->nullable()->comment('触发来源IP');
            $table->text('triggered_info')->nullable()->comment('触发时上下文信息(JSON)');
            $table->unsignedInteger('trigger_count')->default(0)->comment('触发次数');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honeypot_licenses');
    }
};
