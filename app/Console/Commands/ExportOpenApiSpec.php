<?php

namespace App\Console\Commands;

use App\Services\ApiDocsService;
use Illuminate\Console\Command;

class ExportOpenApiSpec extends Command
{
    protected $signature = 'api-docs:export-openapi
                            {--api-version-id= : 指定 API 版本 ID}
                            {--output= : 输出文件路径，默认输出到 storage/api-docs/openapi.json}
                            {--pretty : 美化输出 JSON}';

    protected $description = '导出 OpenAPI 3.0 规范文件';

    public function handle(ApiDocsService $apiDocs): int
    {
        $this->info('导出 OpenAPI 3.0 规范...');

        $apiVersionId = $this->option('api-version-id')
            ? (int) $this->option('api-version-id')
            : null;

        $result = $apiDocs->exportOpenApi($apiVersionId, true);

        $outputPath = $this->option('output')
            ?: storage_path('api-docs/openapi.json');

        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        file_put_contents($outputPath, $result['spec']);

        $this->info("OpenAPI 规范已导出到: {$outputPath}");
        $this->line("  版本: {$result['version']}");
        $this->line("  端点数量: {$result['endpoint_count']}");
        $this->line("  文件大小: " . round(filesize($outputPath) / 1024, 2) . " KB");

        return Command::SUCCESS;
    }
}
