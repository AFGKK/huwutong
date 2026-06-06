<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 注册 Artisan 命令
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
