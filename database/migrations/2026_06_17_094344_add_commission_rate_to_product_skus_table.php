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
        Schema::table('product_skus', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->comment('推广佣金率(%)，留空使用系统默认');
        });
    }

    public function down(): void
    {
        Schema::table('product_skus', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
