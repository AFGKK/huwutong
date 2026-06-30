<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sku_special_prices')) {
            return;
        }
        // 促销活动扩展字段
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'whitelist_customers')) {
                $table->json('whitelist_customers')->nullable()->after('metadata')
                    ->comment('白名单客户ID列表');
            }
            if (!Schema::hasColumn('promotions', 'is_first_order_only')) {
                $table->boolean('is_first_order_only')->default(false)->after('whitelist_customers');
            }
            if (!Schema::hasColumn('promotions', 'is_member_only')) {
                $table->boolean('is_member_only')->default(false)->after('is_first_order_only');
            }
            if (!Schema::hasColumn('promotions', 'member_tier')) {
                $table->string('member_tier', 30)->nullable()->after('is_member_only')
                    ->comment('会员等级要求: silver/gold/platinum');
            }
            if (!Schema::hasColumn('promotions', 'auto_recover')) {
                $table->boolean('auto_recover')->default(true)->after('member_tier')
                    ->comment('到期后自动恢复原价');
            }
            if (!Schema::hasColumn('promotions', 'applicable_skus')) {
                $table->json('applicable_skus')->nullable()->after('applicable_products')
                    ->comment('适用的SKU ID列表');
            }
        });

        // SKU 专享价表（会员价/白名单价）
        if (!Schema::hasTable('sku_special_prices')) {
            Schema::create('sku_special_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sku_id')->index();
                $table->unsignedBigInteger('promotion_id')->nullable()->index();
                $table->string('type', 30)->comment('member/vip/whitelist/flash_sale');
                $table->string('tier', 30)->nullable()->comment('会员等级: silver/gold/platinum');
                $table->decimal('price', 12, 2)->comment('专享价');
                $table->unsignedBigInteger('customer_id')->nullable()->index()->comment('白名单客户ID');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('sku_id')->references('id')->on('product_skus')->cascadeOnDelete();
            });
        }

        // 促销日历事件表
        if (!Schema::hasTable('promotion_calendar_events')) {
            Schema::create('promotion_calendar_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id')->index();
                $table->string('title');
                $table->string('color', 20)->nullable();
                $table->timestamp('start_at');
                $table->timestamp('end_at')->nullable();
                $table->string('status', 20)->default('scheduled');
                $table->timestamps();

                $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'whitelist_customers', 'is_first_order_only', 'is_member_only',
                'member_tier', 'auto_recover', 'applicable_skus',
            ]);
        });
        Schema::dropIfExists('sku_special_prices');
        Schema::dropIfExists('promotion_calendar_events');
    }
};
