<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('编码: one-time/monthly/quarterly/yearly/custom');
            $table->string('name', 50)->comment('显示名称: 一次性/月付/季付/年付');
            $table->integer('months')->nullable()->comment('对应月数，一次性=null');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 插入默认周期
        $now = now();
        DB::table('billing_cycles')->insert([
            ['code' => 'one-time', 'name' => '一次性', 'months' => null, 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'monthly', 'name' => '月付', 'months' => 1, 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'quarterly', 'name' => '季付', 'months' => 3, 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'yearly', 'name' => '年付', 'months' => 12, 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'biennial', 'name' => '两年付', 'months' => 24, 'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'triennial', 'name' => '三年付', 'months' => 36, 'sort_order' => 60, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
