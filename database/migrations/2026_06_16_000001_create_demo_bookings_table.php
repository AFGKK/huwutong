<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_bookings')) return;

        Schema::create('demo_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 200)->comment('公司名称');
            $table->string('contact_name', 100)->comment('联系人');
            $table->string('email', 200)->comment('邮箱');
            $table->string('phone', 50)->nullable()->comment('手机号');
            $table->string('employee_count', 50)->nullable()->comment('员工规模');
            $table->string('product_interest', 500)->nullable()->comment('感兴趣的产品');
            $table->text('message')->nullable()->comment('备注信息');
            $table->string('source', 50)->default('website')->comment('来源: website/landing/pricing/contact');
            $table->string('status', 30)->default('pending')->comment('pending/contacted/scheduled/completed/converted/lost');
            $table->text('admin_notes')->nullable()->comment('管理员备注');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->comment('分配的销售');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('calendly_scheduled_at')->nullable()->comment('Calendly预约时间');
            $table->string('calendly_event_uri', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_bookings');
    }
};
