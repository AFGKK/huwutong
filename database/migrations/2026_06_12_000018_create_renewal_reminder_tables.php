<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 续费提醒模板
        Schema::create('renewal_reminder_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('channel', 30)->default('mail')->comment('mail/sms/in_app');
            $table->integer('days_before')->comment('到期前天数');
            $table->string('subject', 200)->nullable();
            $table->text('content')->nullable();
            $table->text('sms_content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'days_before']);
        });

        // 续费提醒发送记录
        Schema::create('renewal_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('template_name', 100)->nullable();
            $table->string('subject', 200)->nullable();
            $table->string('status', 30)->default('pending')->comment('pending/sent/failed');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_reminder_logs');
        Schema::dropIfExists('renewal_reminder_templates');
    }
};
