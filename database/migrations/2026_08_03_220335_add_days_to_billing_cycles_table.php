<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->integer('days')->nullable()->after('months')->comment('对应天数，0或null表示按整月计算');
        });
    }

    public function down(): void
    {
        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->dropColumn('days');
        });
    }
};
