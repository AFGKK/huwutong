<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_campaigns', function (Blueprint $table) {
            $table->decimal('platform_share_rate', 5, 2)->default(0)->after('cost_per_impression')
                ->comment('平台抽成比例(%), 0表示全部归推广者');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_campaigns', function (Blueprint $table) {
            $table->dropColumn('platform_share_rate');
        });
    }
};
