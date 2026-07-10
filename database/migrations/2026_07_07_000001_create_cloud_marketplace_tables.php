<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 云市场产品/Offer 映射 ───
        if (Schema::hasTable('cloud_marketplace_products')) { return; }
        Schema::create('cloud_marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace');              // aws | azure | gcp
            $table->string('offer_id');                  // AWS: productCode, Azure: planId, GCP: serviceName
            $table->string('offer_name')->nullable();
            $table->string('status')->default('active'); // active | inactive | deprecated
            $table->json('mapping_rules')->nullable();   // 映射到本地 PricingPlan / Product 的规则
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'marketplace', 'offer_id'], 'cmp_product_uniq');
        });

        // ─── 云市场客户订阅（三方来的订阅记录）───
        if (Schema::hasTable('cloud_marketplace_subscriptions')) { return; }
        Schema::create('cloud_marketplace_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace');               // aws | azure | gcp
            $table->string('marketplace_subscription_id'); // 三方订阅ID
            $table->string('offer_id');                   // 关联的 offer
            $table->string('customer_id')->nullable();    // 三方的客户标识（AWS AccountId / Azure TenantId / GCP ProjectId）
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->foreignId('local_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('local_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('local_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('status');                    // subscribed | active | suspended | cancelled | terminated
            $table->string('tier')->nullable();          // 套餐级别
            $table->json('fulfillment_data')->nullable(); // 各平台的扩展数据
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace', 'marketplace_subscription_id'], 'cmp_sub_uniq');
            $table->index(['tenant_id', 'marketplace', 'status'], 'cmp_sub_status_idx');
        });

        // ─── 云市场计量上报记录 ───
        if (Schema::hasTable('cloud_marketplace_metering')) { return; }
        Schema::create('cloud_marketplace_metering', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('cloud_marketplace_subscriptions')->cascadeOnDelete();
            $table->string('marketplace');
            $table->string('dimension');                 // 计量维度（如 api_calls, storage_gb, users）
            $table->decimal('quantity', 14, 4);          // 计量数量
            $table->timestamp('metered_at');             // 计量时间
            $table->timestamp('reported_at')->nullable(); // 上报时间
            $table->string('status')->default('pending'); // pending | reported | failed
            $table->string('error_message')->nullable();
            $table->string('batch_id')->nullable();      // 上报批次ID
            $table->timestamps();

            $table->index(['subscription_id', 'status'], 'cmp_metering_sub_status_idx');
            $table->index(['marketplace', 'status', 'reported_at'], 'cmp_metering_report_idx');
        });

        // ─── 云市场通知日志（SNS/PubSub/Webhook 原始记录）───
        if (Schema::hasTable('cloud_marketplace_notifications')) { return; }
        Schema::create('cloud_marketplace_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace');
            $table->string('notification_type');         // subscribe/unsubscribe/change/renew/webhook
            $table->text('raw_payload');
            $table->string('status')->default('received'); // received | processed | failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace', 'status'], 'cmp_notif_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_marketplace_notifications');
        Schema::dropIfExists('cloud_marketplace_metering');
        Schema::dropIfExists('cloud_marketplace_subscriptions');
        Schema::dropIfExists('cloud_marketplace_products');
    }
};
