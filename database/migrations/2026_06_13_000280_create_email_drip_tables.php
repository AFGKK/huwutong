<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_drip_campaigns')) {
            return;
        }
        Schema::create('email_drip_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('trigger_event', 100)->comment('触发事件');
            $table->string('status', 30)->default('draft')->comment('draft/active/paused/completed');
            $table->text('description')->nullable();
            $table->json('target_filters')->nullable()->comment('目标客户筛选条件');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('email_drip_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_drip_campaigns')->cascadeOnDelete();
            $table->string('name', 200);
            $table->integer('delay_days')->comment('触发后第N天发送');
            $table->string('subject', 500);
            $table->text('content')->comment('邮件内容(HTML)');
            $table->string('template_id', 100)->nullable()->comment('邮件模板引用');
            $table->integer('sort_order')->default(0);
            $table->json('ab_test')->nullable()->comment('A/B测试变体');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'sort_order']);
        });

        Schema::create('email_drip_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_drip_campaigns')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('email_drip_sequences')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('status', 30)->default('pending')->comment('pending/sent/opened/clicked/bounced/failed');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['sequence_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_drip_recipients');
        Schema::dropIfExists('email_drip_sequences');
        Schema::dropIfExists('email_drip_campaigns');
    }
};
