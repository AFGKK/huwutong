<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_titles')) {
            return;
        }
        // 企业发票抬头表
        if (!Schema::hasTable('invoice_titles')) {
            Schema::create('invoice_titles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('title', 200)->comment('发票抬头');
                $table->string('tax_no', 50)->nullable()->comment('税号');
                $table->string('address', 300)->nullable()->comment('地址');
                $table->string('phone', 50)->nullable()->comment('电话');
                $table->string('bank_name', 200)->nullable()->comment('开户行');
                $table->string('bank_account', 50)->nullable()->comment('银行账号');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        // orders 表增加关联
        if (!Schema::hasColumn('orders', 'invoice_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('payment_extra');
                $table->unsignedBigInteger('invoice_title_id')->nullable()->after('invoice_id');
                $table->timestamp('invoice_generated_at')->nullable()->after('invoice_title_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_titles');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'invoice_title_id', 'invoice_generated_at']);
        });
    }
};
