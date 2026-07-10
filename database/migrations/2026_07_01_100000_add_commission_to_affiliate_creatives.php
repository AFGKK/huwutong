<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_creatives', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->nullable()->after('image_url')
                ->comment('素材级佣金金额（元），为空则使用活动默认佣金');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_amount')
                ->comment('素材级佣金比例（%），为空则使用活动默认比例');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_creatives', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_rate']);
        });
    }
};
