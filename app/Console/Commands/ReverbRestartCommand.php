<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReverbRestartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:restart-safely';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '安全重启 Reverb WebSocket 服务';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('正在停止 Reverb 服务...');
        $this->callSilent('reverb:stop');
        sleep(2);

        $this->info('正在启动 Reverb 服务...');
        $this->call('reverb:start', [
            '--host' => config('reverb.servers.reverb.host', '0.0.0.0'),
            '--port' => config('reverb.servers.reverb.port', 8080),
        ]);
    }
}
