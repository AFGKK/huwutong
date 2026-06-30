<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 给 registration_tracking 增加联盟推广字段
        Schema::table('registration_tracking', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('channel_id')->constrained('agents')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->after('agent_id')->constrained('affiliate_campaigns')->nullOnDelete();
            $table->foreignId('creative_id')->nullable()->after('campaign_id')->constrained('affiliate_creatives')->nullOnDelete();
            $table->integer('product_id')->nullable()->after('creative_id')->comment('推广商品ID');
            $table->string('landing_url')->nullable()->after('product_id');
            $table->timestamp('expires_at')->nullable()->after('conversion_type');
        });

        // 给 store_orders 增加推广关联字段
        if (!Schema::hasTable('store_orders')) {
            return;
        }
        Schema::table('store_orders', function (Blueprint $table) {
            $table->foreignId('affiliate_agent_id')->nullable()->after('user_id')->constrained('agents')->nullOnDelete();
            $table->string('referral_code', 50)->nullable()->after('affiliate_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('registration_tracking', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['creative_id']);
            $table->dropColumn(['agent_id', 'campaign_id', 'creative_id', 'product_id', 'landing_url', 'expires_at']);
        });

        Schema::table('store_orders', function (Blueprint $table) {
            $table->dropForeign(['affiliate_agent_id']);
            $table->dropColumn(['affiliate_agent_id', 'referral_code']);
        });
    }
};
