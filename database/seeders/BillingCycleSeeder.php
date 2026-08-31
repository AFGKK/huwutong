<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingCycleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $cycles = [
            ['code' => 'one-time', 'name' => '一次性', 'months' => null, 'days' => null, 'sort_order' => 10, 'is_active' => true],
            ['code' => 'daily', 'name' => '日付', 'months' => 0, 'days' => 1, 'sort_order' => 15, 'is_active' => true],
            ['code' => 'weekly', 'name' => '周付', 'months' => 0, 'days' => 7, 'sort_order' => 18, 'is_active' => true],
            ['code' => 'monthly', 'name' => '月付', 'months' => 1, 'days' => null, 'sort_order' => 20, 'is_active' => true],
            ['code' => 'quarterly', 'name' => '季付', 'months' => 3, 'days' => null, 'sort_order' => 30, 'is_active' => true],
            ['code' => 'semi-annual', 'name' => '半年付', 'months' => 6, 'days' => null, 'sort_order' => 35, 'is_active' => true],
            ['code' => 'yearly', 'name' => '年付', 'months' => 12, 'days' => null, 'sort_order' => 40, 'is_active' => true],
            ['code' => 'biennial', 'name' => '两年付', 'months' => 24, 'days' => null, 'sort_order' => 50, 'is_active' => true],
            ['code' => 'triennial', 'name' => '三年付', 'months' => 36, 'days' => null, 'sort_order' => 60, 'is_active' => true],
        ];

        foreach ($cycles as $cycle) {
            DB::table('billing_cycles')->updateOrInsert(
                ['code' => $cycle['code']],
                array_merge($cycle, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
