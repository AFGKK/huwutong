<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ApiDocsController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ScanApiRoutes extends Command
{
    protected $signature = 'api-docs:scan-routes {--api-version-id=}';
    protected $description = '扫描所有 API 路由并写入文档数据库';

    public function handle(): int
    {
        $this->info('开始扫描 API 路由...');

        $controller = app(ApiDocsController::class);
        $request = new Request();

        if ($this->option('api-version-id')) {
            $request->merge(['api_version_id' => (int) $this->option('api-version-id')]);
        }

        try {
            $result = $controller->scanRoutes($request);
            $data = $result->getData(true);

            if (($data['success'] ?? false)) {
                $this->info($data['data']['message'] ?? '扫描完成');
                return Command::SUCCESS;
            } else {
                $this->error($data['message'] ?? '扫描失败');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('扫描异常: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
