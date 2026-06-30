<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('after_sales_status', 30)->nullable()->after('status')
                ->comment('售后工单状态: null=无售后, pending=待处理, in_progress=处理中, resolved=已解决, closed=已关闭');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('after_sales_status');
        });
    }
};
