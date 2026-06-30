<?php

namespace App\Services;

use App\Models\ApiDocEndpoint;
use App\Models\ApiDocSchema;
use Illuminate\Support\Facades\Route;

/**
 * Postman Collection 生成服务 (M2-87)
 *
 * 基于现有 API 端点数据自动生成 Postman Collection v2.1 JSON。
 */
class PostmanCollectionService
{
    /**
     * 生成完整 Postman Collection JSON
     */
    public function generateCollection(): array
    {
        $config = config('postman.collection');
        $info = [
            'name' => $config['name'],
            'description' => $config['description'],
            'schema' => $config['schema'],
        ];

        $items = $this->buildItems();
        $variable = $this->buildVariables();

        return [
            'info' => $info,
            'item' => $items,
            'variable' => $variable,
        ];
    }

    /**
     * 生成环境配置文件 JSON
     */
    public function generateEnvironment(string $envName): ?array
    {
        $envs = config('postman.environments', []);
        $env = $envs[$envName] ?? null;
        if (!$env) return null;

        return [
            'name' => $env['name'],
            'values' => $env['values'],
        ];
    }

    /**
     * 获取所有可用环境列表
     */
    public function getEnvironments(): array
    {
        $envs = config('postman.environments', []);
        $result = [];
        foreach ($envs as $key => $env) {
            $result[] = [
                'key' => $key,
                'name' => $env['name'],
                'variable_count' => count($env['values']),
            ];
        }
        return $result;
    }

    /**
     * 统计信息
     */
    public function stats(): array
    {
        $includeGroups = config('postman.include_groups', []);
        $totalEndpoints = ApiDocEndpoint::count();
        $filteredEndpoints = empty($includeGroups)
            ? $totalEndpoints
            : ApiDocEndpoint::whereIn('group', $includeGroups)->count();

        return [
            'total_endpoints' => $totalEndpoints,
            'filtered_endpoints' => $filteredEndpoints,
            'environments' => count(config('postman.environments', [])),
            'include_groups' => $includeGroups,
            'examples' => count(config('postman.examples', [])),
        ];
    }

    /**
     * 构建 Collection Items（按分组）
     */
    protected function buildItems(): array
    {
        $items = [];

        // 1. 示例请求
        $examples = config('postman.examples', []);
        if (!empty($examples)) {
            $exampleItems = [];
            foreach ($examples as $ex) {
                $exampleItems[] = $this->createRequestItem($ex['name'], $ex['method'], $ex['path'], $ex['headers'] ?? [], $ex['body'] ?? '');
            }
            $items[] = [
                'name' => '📖 快速示例',
                'item' => $exampleItems,
            ];
        }

        // 2. 从数据库读取 API 端点
        $includeGroups = config('postman.include_groups', []);
        $endpoints = ApiDocEndpoint::query()
            ->when(!empty($includeGroups), fn($q) => $q->whereIn('group', $includeGroups))
            ->orderBy('group')
            ->orderBy('method')
            ->get()
            ->groupBy('group');

        foreach ($endpoints as $group => $eps) {
            $groupItems = [];
            foreach ($eps as $ep) {
                $headers = [];
                if ($ep->auth_required ?? true) {
                    $headers[] = 'Authorization: Bearer {{api_key}}';
                }
                $headers[] = 'Content-Type: application/json';
                if ($ep->accept_header ?? null) {
                    $headers[] = "Accept: {$ep->accept_header}";
                }

                $groupItems[] = $this->createRequestItem(
                    $ep->summary ?: "{$ep->method} {$ep->path}",
                    $ep->method,
                    $ep->path,
                    $headers,
                    $ep->request_body ?? '',
                    $ep->description ?? ''
                );
            }

            $items[] = [
                'name' => $group,
                'item' => $groupItems,
            ];
        }

        return $items;
    }

    /**
     * 创建单个请求 Item
     */
    protected function createRequestItem(string $name, string $method, string $path, array $headers = [], string $body = '', string $description = ''): array
    {
        $item = [
            'name' => $name,
            'request' => [
                'method' => $method,
                'header' => $this->parseHeaders($headers),
                'url' => [
                    'raw' => '{{base_url}}' . $path,
                    'host' => ['{{base_url}}'],
                    'path' => explode('/', trim($path, '/')),
                    'variable' => $this->extractPathVariables($path),
                ],
                'description' => $description,
            ],
        ];

        // 添加请求体
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($body)) {
            $item['request']['body'] = [
                'mode' => 'raw',
                'raw' => $body,
                'options' => [
                    'raw' => ['language' => 'json'],
                ],
            ];
        }

        return $item;
    }

    /**
     * 构建 Collection Variables
     */
    protected function buildVariables(): array
    {
        $vars = [];
        $envs = config('postman.environments', []);
        // 从第一个环境提取变量
        $first = reset($envs);
        if ($first && isset($first['values'])) {
            foreach ($first['values'] as $v) {
                $vars[] = [
                    'key' => $v['key'],
                    'value' => $v['value'],
                    'type' => $v['type'] ?? 'string',
                ];
            }
        }
        // 添加额外常用变量
        $extraVars = [
            ['key' => 'fingerprint', 'value' => '2:a1b2c3d4e5f6...', 'type' => 'string'],
            ['key' => 'customer_id', 'value' => '1', 'type' => 'string'],
            ['key' => 'product_id', 'value' => '1', 'type' => 'string'],
        ];
        foreach ($extraVars as $v) {
            $vars[] = $v;
        }
        return $vars;
    }

    /**
     * 提取路径变量
     */
    protected function extractPathVariables(string $path): array
    {
        $vars = [];
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        foreach ($matches[1] as $var) {
            $vars[] = [
                'key' => $var,
                'value' => '',
            ];
        }
        return $vars;
    }

    /**
     * 解析请求头
     */
    protected function parseHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $h) {
            if (str_contains($h, ':')) {
                [$key, $value] = explode(':', $h, 2);
                $result[] = [
                    'key' => trim($key),
                    'value' => trim($value),
                    'type' => 'text',
                ];
            }
        }
        return $result;
    }
}
