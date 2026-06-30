<?php

namespace App\Console\Commands;

use App\Services\Grpc\GrpcManagerService;
use Illuminate\Console\Command;

class GrpcServerCommand extends Command
{
    protected $signature = 'grpc:serve
                           {--port=50051 : 监听端口}
                           {--host=0.0.0.0 : 监听地址}
                           {--workers=4 : Worker 进程数}';

    protected $description = '启动 gRPC 服务端（HTTP/2 模式）';

    public function handle(GrpcManagerService $manager): int
    {
        $mode = config('grpc.mode', 'rest');

        if ($mode === 'grpc') {
            $this->error('gRPC 原生模式需要使用 RoadRunner 或 Swoole 启动');
            $this->info('请参考: https://roadrunner.dev/docs/php-grpc');
            return self::FAILURE;
        }

        $host = $this->option('host');
        $port = (int) $this->option('port');
        $workers = (int) $this->option('workers');

        $this->components->info("启动 gRPC HTTP/2 服务端: {$host}:{$port}");
        $this->components->twoColumnDetail('工作模式', config('grpc.mode', 'rest'));
        $this->components->twoColumnDetail('Worker 数', (string) $workers);
        $this->newLine();

        if ($mode === 'rest') {
            $this->warn('当前为 REST 回退模式。生产环境建议切换为 grpc 或 http2 模式。');
            $this->warn('设置环境变量: GRPC_MODE=http2 或安装 grpc PHP 扩展后设为 grpc');
        }

        // HTTP/2 模式下启动内置开发服务器
        if ($mode === 'http2') {
            $this->startHttp2Server($host, $port);
        } else {
            // REST 模式下仅显示配置
            $this->showRestEndpoints($manager);
        }

        return self::SUCCESS;
    }

    protected function startHttp2Server(string $host, int $port): void
    {
        $this->info("服务端运行在 http://{$host}:{$port}");
        $this->info("按 Ctrl+C 停止");
        $this->newLine();

        // 简单的 HTTP/2 路由处理
        $server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);

        if (!$server) {
            $this->error("启动失败: {$errstr} ({$errno})");
            return;
        }

        stream_set_blocking($server, false);

        while (true) {
            $conn = @stream_socket_accept($server, 5);
            if ($conn) {
                // 简化处理：读取请求并响应
                $request = fread($conn, 4096);
                $response = "HTTP/1.1 200 OK\r\nContent-Type: application/grpc+json\r\n\r\n{\"status\": \"running\"}";
                fwrite($conn, $response);
                fclose($conn);
            }

            // 检查中断
            if (pcntl_signal_dispatch()) {
                break;
            }
        }

        fclose($server);
    }

    protected function showRestEndpoints(GrpcManagerService $manager): void
    {
        $this->components->section('REST 回退端点');
        $endpoints = $manager->getEndpoints();

        $rows = [];
        foreach ($endpoints as $service => $ep) {
            $rows[] = [
                $service,
                $ep['address'],
                '/api/v1/grpc/' . $service,
            ];
        }

        $this->table(['服务', 'gRPC 地址', 'REST 端点'], $rows);

        $this->newLine();
        $this->info('健康检查:');
        $health = $manager->healthCheck();
        foreach ($health['results'] as $name => $result) {
            $icon = $result['healthy'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$icon} {$name}: " . ($result['latency_ms'] > 0 ? "{$result['latency_ms']}ms" : 'N/A'));
        }
    }
}
