<?php

namespace Database\Seeders;

use App\Models\OfficialAccount;
use Illuminate\Database\Seeder;

class BlogOaAccountSeeder extends Seeder
{
    public function run(): void
    {
        OfficialAccount::firstOrCreate(
            ['slug' => 'hwt-blog'],
            [
                'name' => 'HWT开发者博客',
                'description' => '互物通官方开发者博客 — 集成教程、最佳实践、客户案例与产品更新',
                'owner_id' => 1,
                'status' => 'active',
            ]
        );
    }
}
