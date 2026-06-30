<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_campaigns', function (Blueprint $table) {
            $table->decimal('budget_deposited', 14, 2)->default(0)->after('budget_total')
                ->comment('已存入预算金额');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_campaigns', function (Blueprint $table) {
            $table->dropColumn('budget_deposited');
        });
    }
};
