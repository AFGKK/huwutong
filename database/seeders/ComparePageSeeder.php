<?php

namespace Database\Seeders;

use App\Services\ComparePageService;
use Illuminate\Database\Seeder;

class ComparePageSeeder extends Seeder
{
    public function run(): void
    {
        $config = app(ComparePageService::class)->syncFromConfigFile(false);
        $this->command?->info('compare_page setting ready ('.count($config['competitors'] ?? []).' competitors)');
    }
}
