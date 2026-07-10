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
        Schema::table('affiliate_campaigns', function (Blueprint $table) {
            $table->string('billing_mode', 10)->default('cpa')->after('type')->comment('计费模式: cpa=按转化, cpc=按点击, cpm=按展示');
            $table->decimal('cost_per_click', 10, 2)->nullable()->after('budget_used')->comment('CPC 每次点击费用');
            $table->decimal('cost_per_impression', 10, 2)->nullable()->after('cost_per_click')->comment('CPM 每千次展示费用');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_campaigns', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'cost_per_click', 'cost_per_impression']);
        });
    }
};
