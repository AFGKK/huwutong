<?php

namespace App\Services;

use App\Models\ApiChangelog;
use App\Models\ApiDocCodeSnippet;
use App\Models\ApiDocEndpoint;
use App\Models\ApiEndpointSnapshot;
use App\Models\ApiDocFavorite;
use App\Models\ApiDocSchema;
use App\Models\ApiDocTag;
use App\Models\ApiSdkConfig;
use App\Models\ApiTestRequest;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API 文档门户服务
 *
 * 提供：
 * - 交互式 API 文档浏览
 * - API 测试控制台
 * - SDK 代码生成与下载
 * - Schema 注册表
 * - 变更日志管理
 * - 自动文档抓取
 */
class ApiDocsService
{
    // ─── 端点管理 ───

    public function getGroups(): array
    {
        return [
            'auth' => '认证与授权',
            'licenses' => '许可证管理',
            'subscriptions' => '订阅管理',
            'invoices' => '发票管理',
            'customers' => '客户管理',
            'products' => '产品管理',
            'api-keys' => 'API 密钥',
            'webhooks' => 'Webhook',
            'features' => '功能开关',
            'devices' => '设备管理',
            'analytics' => '分析与报表',
            'billing' => '计费系统',
            'admin' => '系统管理',
            'audit' => '审计与合规',
        ];
    }

    public function getEndpointList(array $filters = []): array
    {
        $query = ApiDocEndpoint::with('apiVersion')->orderBy('sort_order')->orderBy('path');

        if (!empty($filters['api_version_id'])) {
            $query->where('api_version_id', $filters['api_version_id']);
        }
        if (!empty($filters['method'])) {
            $query->where('method', strtoupper($filters['method']));
        }
        if (!empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }
        if (!empty($filters['tag'])) {
            $query->where('tag', $filters['tag']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('path', 'like', "%{$q}%")
                    ->orWhere('summary', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 100), 200);
        return $query->paginate($perPage)->toArray();
    }

    public function getEndpoint(int $id): ?ApiDocEndpoint
    {
        return ApiDocEndpoint::with(['apiVersion', 'snippets'])->findOrFail($id);
    }

    public function createEndpoint(array $data): ApiDocEndpoint
    {
        $data['method'] = strtoupper($data['method']);
        return ApiDocEndpoint::create($data);
    }

    public function updateEndpoint(ApiDocEndpoint $endpoint, array $data): ApiDocEndpoint
    {
        if (isset($data['method'])) {
            $data['method'] = strtoupper($data['method']);
        }
        $endpoint->update($data);
        return $endpoint->fresh();
    }

    public function deleteEndpoint(ApiDocEndpoint $endpoint): void
    {
        $endpoint->snippets()->delete();
        $endpoint->delete();
    }

    // ─── Schema 注册表 ───

    public function getSchemas(): array
    {
        return ApiDocSchema::orderBy('name')->get()->all();
    }

    public function createOrUpdateSchema(string $name, array $data): ApiDocSchema
    {
        return ApiDocSchema::updateOrCreate(['name' => $name], $data);
    }

    // ─── 代码片段 ───

    public function addCodeSnippet(array $data): ApiDocCodeSnippet
    {
        return ApiDocCodeSnippet::create($data);
    }

    public function deleteCodeSnippet(int $id): void
    {
        ApiDocCodeSnippet::findOrFail($id)->delete();
    }

    // ─── 测试控制台 ───

    public function sendTestRequest(int $userId, array $data): array
    {
        $start = microtime(true);

        try {
            $method = strtolower($data['method']);
            $url = $data['url'];
            $headers = $data['headers'] ?? [];
            $body = $data['body'] ?? null;

            // 构建 HTTP 请求
            $http = Http::withHeaders($headers)
                ->timeout(30)
                ->withoutVerifying();

            if ($body) {
                $response = $http->$method($url, $body);
            } else {
                $response = $http->$method($url);
            }

            $responseTime = (int) ((microtime(true) - $start) * 1000);

            $result = [
                'status' => 'success',
                'response_status' => $response->status(),
                'response' => [
                    'headers' => $response->headers(),
                    'body' => $this->parseResponseBody($response->body()),
                    'size' => strlen($response->body()),
                ],
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            $result = [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }

        // 记录请求
        ApiTestRequest::create([
            'user_id' => $userId,
            'endpoint_id' => $data['endpoint_id'] ?? null,
            'method' => strtoupper($data['method']),
            'url' => $data['url'],
            'headers' => $data['headers'] ?? [],
            'body' => $data['body'] ?? null,
            'response' => $result['response'] ?? null,
            'response_status' => $result['response_status'] ?? null,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'status' => $result['status'],
            'error_message' => $result['error_message'] ?? null,
        ]);

        return $result;
    }

    public function getTestHistory(int $userId, int $limit = 20): array
    {
        return ApiTestRequest::where('user_id', $userId)
            ->with('endpoint')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    // ─── SDK 配置 ───

    public function getActiveSdks(): array
    {
        return ApiSdkConfig::where('is_active', true)->orderBy('language')->get()->all();
    }

    public function createOrUpdateSdk(string $language, array $data): ApiSdkConfig
    {
        return ApiSdkConfig::updateOrCreate(['language' => $language], $data);
    }

    /**
     * 生成 SDK 代码包（基于端点定义生成）
     */
    public function generateSdkClient(string $language, array $endpointIds = []): array
    {
        $endpoints = ApiDocEndpoint::whereIn('id', $endpointIds)
            ->orWhere('status', 'active')
            ->orderBy('group')
            ->orderBy('path')
            ->get();

        $sdkConfig = ApiSdkConfig::where('language', $language)->first();
        $baseUrl = config('app.url') . '/api';

        $code = match ($language) {
            'php' => $this->generatePhpClient($endpoints, $sdkConfig, $baseUrl),
            'python' => $this->generatePythonClient($endpoints, $sdkConfig, $baseUrl),
            'javascript' => $this->generateJsClient($endpoints, $sdkConfig, $baseUrl),
            'go' => $this->generateGoClient($endpoints, $sdkConfig, $baseUrl),
            'java' => $this->generateJavaClient($endpoints, $sdkConfig, $baseUrl),
            'ruby' => $this->generateRubyClient($endpoints, $sdkConfig, $baseUrl),
            default => '# Language not supported',
        };

        return [
            'language' => $language,
            'version' => $sdkConfig->version ?? '1.0.0',
            'code' => $code,
            'endpoint_count' => $endpoints->count(),
        ];
    }

    protected function generatePhpClient($endpoints, $sdkConfig, string $baseUrl): string
    {
        $setup = $sdkConfig->setup_code ?? "\$client = new GuzzleHttp\\Client(['base_uri' => '{$baseUrl}']);";
        $lines = ["<?php", "", "// " . ($sdkConfig->name ?? "API Client") . " v" . ($sdkConfig->version ?? "1.0.0"), "// 安装: " . ($sdkConfig->install_command ?? "composer require hwt/api-client"), "", $setup, ""];
        $groups = $endpoints->groupBy('group');
        foreach ($groups as $group => $eps) {
            $lines[] = "// ─── {$group} ───";
            foreach ($eps as $ep) {
                $funcName = $this->pathToFunction($ep->path, $ep->method);
                $params = $this->extractParamNames($ep->parameters);
                $paramStr = !empty($params) ? '$' . implode(', $', $params) : '';
                $lines[] = "function {$funcName}({$paramStr}) {";
                $lines[] = "    return \$client->request('{$ep->method}', '{$ep->path}');";
                $lines[] = "}";
                $lines[] = "";
            }
        }
        return implode("\n", $lines);
    }

    protected function generatePythonClient($endpoints, $sdkConfig, string $baseUrl): string
    {
        $lines = ["# " . ($sdkConfig->name ?? "API Client") . " v" . ($sdkConfig->version ?? "1.0.0"), "# 安装: " . ($sdkConfig->install_command ?? "pip install hwt-api"), "", "import requests", "", ""];
        $groups = $endpoints->groupBy('group');
        $lines[] = "class HwtApiClient:";
        $lines[] = "    def __init__(self, api_key: str, base_url: str = '{$baseUrl}'):";
        $lines[] = "        self.base_url = base_url.rstrip('/')";
        $lines[] = "        self.headers = {'Authorization': f'Bearer {api_key}', 'Accept': 'application/json'}";
        $lines[] = "";
        foreach ($groups as $group => $eps) {
            $lines[] = "    # ─── {$group} ───";
            foreach ($eps as $ep) {
                $funcName = $this->pathToFunction($ep->path, $ep->method);
                $lines[] = "    def {$funcName}(self):";
                $lines[] = "        return requests.{$this->methodToPython(strtolower($ep->method))}(";
                $lines[] = "            f'{self.base_url}{$ep->path}',";
                $lines[] = "            headers=self.headers,";
                $lines[] = "        ).json()";
                $lines[] = "";
            }
        }
        return implode("\n", $lines);
    }

    protected function generateJsClient($endpoints, $sdkConfig, string $baseUrl): string
    {
        $lines = ["// " . ($sdkConfig->name ?? "API Client") . " v" . ($sdkConfig->version ?? "1.0.0"), "// 安装: " . ($sdkConfig->install_command ?? "npm install hwt-api-client"), "", "import axios from 'axios';", "", "class HwtApiClient {", "    constructor(apiKey, baseURL = '{$baseUrl}') {", "        this.client = axios.create({", "            baseURL,", "            headers: { Authorization: `Bearer \${apiKey}`, Accept: 'application/json' },", "        });", "    }", ""];
        $groups = $endpoints->groupBy('group');
        foreach ($groups as $group => $eps) {
            $lines[] = "    // ── {$group} ──";
            foreach ($eps as $ep) {
                $funcName = $this->pathToFunction($ep->path, $ep->method);
                $lines[] = "    async {$funcName}() {";
                $lines[] = "        return this.client.{$this->methodToJs(strtolower($ep->method))}('{$ep->path}');";
                $lines[] = "    }";
                $lines[] = "";
            }
        }
        $lines[] = "}";
        $lines[] = "";
        $lines[] = "export { HwtApiClient };";
        return implode("\n", $lines);
    }

    protected function generateGoClient($endpoints, $sdkConfig, string $baseUrl): string
    {
        return "package hwt\n\nimport (\n\t\"bytes\"\n\t\"encoding/json\"\n\t\"fmt\"\n\t\"io\"\n\t\"net/http\"\n)\n\n// " . ($sdkConfig->name ?? "ApiClient") . " v" . ($sdkConfig->version ?? "1.0.0") . "\ntype ApiClient struct {\n\tbaseURL    string\n\tapiKey     string\n\thttpClient *http.Client\n}\n\nfunc NewClient(apiKey string) *ApiClient {\n\treturn &ApiClient{\n\t\tbaseURL:    \"{$baseUrl}\",\n\t\tapiKey:     apiKey,\n\t\thttpClient: &http.Client{},\n\t}\n}\n\n// SDK 代码生成器 - Go 语言支持 " . count($endpoints) . " 个端点\nfunc (c *ApiClient) request(method, path string, body interface{}) ([]byte, error) {\n\tvar reqBody io.Reader\n\tif body != nil {\n\t\tdata, _ := json.Marshal(body)\n\t\treqBody = bytes.NewBuffer(data)\n\t}\n\treq, _ := http.NewRequest(method, c.baseURL+path, reqBody)\n\treq.Header.Set(\"Authorization\", \"Bearer \"+c.apiKey)\n\treq.Header.Set(\"Accept\", \"application/json\")\n\tresp, err := c.httpClient.Do(req)\n\tif err != nil {\n\t\treturn nil, err\n\t}\n\tdefer resp.Body.Close()\n\treturn io.ReadAll(resp.Body)\n}\n";
    }

    protected function generateJavaClient($endpoints, $sdkConfig, string $baseUrl): string
    {
        $pkg = str_replace(['-', '.'], ['', ''], parse_url($baseUrl, PHP_URL_HOST) ?? 'hwt');
        return "package com.{$pkg}.api;\n\nimport java.net.URI;\nimport java.net.http.HttpClient;\nimport java.net.http.HttpRequest;\nimport java.net.http.HttpResponse;\n\npublic class HwtApiClient {\n    private final HttpClient client;\n    private final String baseUrl = \"{$baseUrl}\";\n    private final String apiKey;\n\n    public HwtApiClient(String apiKey) {\n        this.apiKey = apiKey;\n        this.client = HttpClient.newHttpClient();\n    }\n\n    private String request(String method, String path) throws Exception {\n        HttpRequest req = HttpRequest.newBuilder()\n            .uri(URI.create(baseUrl + path))\n            .header(\"Authorization\", \"Bearer \" + apiKey)\n            .header(\"Accept\", \"application/json\")\n            .method(method, HttpRequest.BodyPublishers.noBody())\n            .build();\n        HttpResponse<String> resp = client.send(req, HttpResponse.BodyHandlers.ofString());\n        return resp.body();\n    }\n\n    // SDK 覆盖 " . count($endpoints) . " 个端点\n}\n";
    }

    protected function generateRubyClient($endpoints, $sdkConfig, string $baseUrl): string
    {
        return "# " . ($sdkConfig->name ?? "ApiClient") . " v" . ($sdkConfig->version ?? "1.0.0") . "\n# 安装: " . ($sdkConfig->install_command ?? "gem install hwt-api") . "\n\nrequire 'net/http'\nrequire 'json'\n\nclass HwtApiClient\n  def initialize(api_key, base_url = '#{$baseUrl}')\n    @api_key = api_key\n    @base_url = base_url\n  end\n\n  def request(method, path, body = nil)\n    uri = URI(\"#{@base_url}#{path}\")\n    http = Net::HTTP.new(uri.host, uri.port)\n    http.use_ssl = uri.scheme == 'https'\n    req = Net::HTTP.const_get(method.capitalize).new(uri)\n    req['Authorization'] = \"Bearer #{@api_key}\"\n    req['Accept'] = 'application/json'\n    req.body = body.to_json if body\n    res = http.request(req)\n    JSON.parse(res.body)\n  end\n\n  # SDK 覆盖 #{count($endpoints)} 个端点\nend\n";
    }

    // ─── 变更日志 ───

    public function getChangelogs(array $filters = []): array
    {
        $query = ApiChangelog::orderByDesc('release_date')->orderByDesc('created_at');
        if (!empty($filters['version'])) {
            $query->where('version', $filters['version']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        return $query->limit(50)->get()->all();
    }

    public function createChangelog(array $data): ApiChangelog
    {
        return ApiChangelog::create($data);
    }

    // ─── 仪表盘 ───

    public function getDashboard(): array
    {
        $endpointCount = ApiDocEndpoint::count();
        $activeEndpoints = ApiDocEndpoint::where('status', 'active')->count();
        $schemaCount = ApiDocSchema::count();
        $sdkCount = ApiSdkConfig::where('is_active', true)->count();
        $changelogCount = ApiChangelog::count();
        $testCount = ApiTestRequest::count();

        $groups = ApiDocEndpoint::selectRaw('`group`, COUNT(*) as cnt')
            ->groupBy('group')
            ->orderByDesc('cnt')
            ->get()
            ->pluck('cnt', 'group')
            ->toArray();

        $methods = ApiDocEndpoint::selectRaw('method, COUNT(*) as cnt')
            ->groupBy('method')
            ->orderByDesc('cnt')
            ->get()
            ->pluck('cnt', 'method')
            ->toArray();

        return [
            'stats' => [
                'total_endpoints' => $endpointCount,
                'active_endpoints' => $activeEndpoints,
                'total_schemas' => $schemaCount,
                'active_sdks' => $sdkCount,
                'total_changelogs' => $changelogCount,
                'total_tests' => $testCount,
            ],
            'by_group' => $groups,
            'by_method' => $methods,
        ];
    }

    // ─── 版本差异对比 ───

    /**
     * 对比两个 API 版本之间的端点差异
     *
     * 返回 added/removed/changed/unchanged 四类端点列表
     */
    public function diffVersions(?int $fromVersionId, ?int $toVersionId): array
    {
        $fromEndpoints = $fromVersionId
            ? ApiDocEndpoint::where('api_version_id', $fromVersionId)->get()->keyBy(fn($e) => $e->method . ' ' . $e->path)
            : collect();

        $toEndpoints = $toVersionId
            ? ApiDocEndpoint::where('api_version_id', $toVersionId)->get()->keyBy(fn($e) => $e->method . ' ' . $e->path)
            : collect();

        $added = [];
        $removed = [];
        $changed = [];
        $unchanged = [];

        $allKeys = $fromEndpoints->keys()->merge($toEndpoints->keys())->unique();

        foreach ($allKeys as $key) {
            $from = $fromEndpoints->get($key);
            $to = $toEndpoints->get($key);

            if (!$from && $to) {
                $added[] = $to;
            } elseif ($from && !$to) {
                $removed[] = $from;
            } elseif ($from && $to) {
                $fromHash = md5(json_encode($from->toArray()));
                $toHash = md5(json_encode($to->toArray()));
                if ($fromHash !== $toHash) {
                    $changed[] = ['from' => $from, 'to' => $to];
                } else {
                    $unchanged[] = $to;
                }
            }
        }

        return compact('added', 'removed', 'changed', 'unchanged');
    }

    // ─── 公开 API 文档 ───

    /**
     * 获取公开 API 文档（按版本分组的端点列表，仅活跃端点）
     */
    public function getPublicDocs(?string $versionSlug = null): array
    {
        $query = ApiDocEndpoint::with(['apiVersion', 'snippets'])
            ->where('status', 'active')
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('path');

        if ($versionSlug) {
            $version = \App\Models\ApiVersion::where('version', $versionSlug)->first();
            if ($version) {
                $query->where('api_version_id', $version->id);
            }
        }

        $endpoints = $query->get();

        $grouped = [];
        foreach ($endpoints as $ep) {
            $group = $ep->group ?: 'default';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [
                    'group' => $group,
                    'group_label' => $this->getGroups()[$group] ?? $group,
                    'endpoints' => [],
                ];
            }
            $grouped[$group]['endpoints'][] = $ep;
        }

        $versionInfo = null;
        if ($versionSlug) {
            $versionInfo = \App\Models\ApiVersion::where('version', $versionSlug)->first();
        }
        if (!$versionInfo) {
            $versionInfo = \App\Models\ApiVersion::where('is_default', true)
                ->orWhere('status', 'active')
                ->first();
        }

        return [
            'version' => $versionInfo ? [
                'version' => $versionInfo->version,
                'name' => $versionInfo->name,
                'status' => $versionInfo->status,
                'base_path' => $versionInfo->base_path,
            ] : null,
            'groups' => array_values($grouped),
            'total_endpoints' => $endpoints->count(),
        ];
    }

    // ─── M3-09 增强功能 ──────────────────────────────────────

    // ─── 端点收藏 ───

    public function toggleFavorite(int $userId, int $endpointId, ?string $note = null): array
    {
        $existing = ApiDocFavorite::where('user_id', $userId)
            ->where('endpoint_id', $endpointId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['favorited' => false];
        }

        ApiDocFavorite::create([
            'user_id' => $userId,
            'endpoint_id' => $endpointId,
            'note' => $note,
        ]);

        return ['favorited' => true];
    }

    public function getUserFavorites(int $userId): array
    {
        return ApiDocFavorite::with('endpoint')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    // ─── OpenAPI 3.0 导出 ───

    public function exportOpenApi(?int $apiVersionId = null, bool $pretty = true): array
    {
        $query = ApiDocEndpoint::with('apiVersion')->orderBy('group')->orderBy('path');
        if ($apiVersionId) {
            $query->where('api_version_id', $apiVersionId);
        }

        $endpoints = $query->get();
        $version = $endpoints->first()?->apiVersion;

        $openApi = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name') . ' API',
                'description' => 'API 文档门户 - 自动生成的 OpenAPI 3.0 规范',
                'version' => $version?->version ?? '1.0.0',
                'contact' => [
                    'name' => 'API Support',
                    'email' => config('app.admin_email', 'support@example.com'),
                ],
            ],
            'servers' => [
                ['url' => config('app.url') . '/api', 'description' => 'Production'],
                ['url' => config('app.url') . '/api', 'description' => 'Staging'],
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'API Key',
                    ],
                ],
                'schemas' => $this->buildOpenApiSchemas(),
            ],
            'tags' => $this->buildOpenApiTags($endpoints),
        ];

        foreach ($endpoints as $ep) {
            $path = $ep->path;
            $method = strtolower($ep->method);

            $pathItem = [
                'summary' => $ep->summary ?? '',
                'description' => $ep->description ?? '',
                'tags' => [$this->getGroups()[$ep->group] ?? $ep->group ?? 'default'],
                'parameters' => $this->buildOpenApiParameters($ep->parameters),
                'responses' => $this->buildOpenApiResponses($ep->responses),
                'security' => [['BearerAuth' => []]],
            ];

            if (!empty($ep->request_body)) {
                $pathItem['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $ep->request_body,
                            ],
                        ],
                    ],
                ];
            }

            if (!empty($ep->example_request)) {
                $pathItem['x-example-request'] = $ep->example_request;
            }
            if (!empty($ep->example_response)) {
                $pathItem['x-example-response'] = $ep->example_response;
            }

            $openApi['paths'][$path][$method] = $pathItem;
        }

        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0;
        return [
            'spec' => json_encode($openApi, $flags),
            'endpoint_count' => $endpoints->count(),
            'version' => $version?->version ?? '1.0.0',
        ];
    }

    // ─── 生成 cURL 示例 ───

    public function generateCurlSnippet(ApiDocEndpoint $endpoint): string
    {
        $method = strtoupper($endpoint->method);
        $url = config('app.url') . $endpoint->path;
        $lines = ["curl -X {$method} \\"];
        $lines[] = "  '{$url}' \\";
        $lines[] = "  -H 'Authorization: Bearer YOUR_API_KEY' \\";
        $lines[] = "  -H 'Accept: application/json' \\";
        $lines[] = "  -H 'Content-Type: application/json' \\";

        if (!empty($endpoint->request_body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = json_encode($endpoint->example_request ?: $endpoint->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $lines[] = "  -d '{$body}'";
        }

        return implode("\n", $lines);
    }

    // ─── 多语言代码片段自动生成 ───

    public function autoGenerateSnippets(ApiDocEndpoint $endpoint): array
    {
        $curl = $this->generateCurlSnippet($endpoint);
        $method = strtolower($endpoint->method);
        $url = config('app.url') . $endpoint->path;
        $hasBody = !empty($endpoint->request_body) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']);

        $snippets = [];

        // cURL
        $snippets[] = ['language' => 'curl', 'title' => 'cURL', 'code' => $curl, 'sort_order' => 1];

        // PHP (Guzzle)
        $phpBody = $hasBody ? ",\n        'json' => " . json_encode($endpoint->example_request ?: $endpoint->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
        $php = "use Illuminate\Support\Facades\Http;\n\n\$response = Http::withHeaders([\n    'Authorization' => 'Bearer YOUR_API_KEY',\n    'Accept' => 'application/json',\n])" . ($phpBody ? "\n    ->withBody(json_encode(" . json_encode($endpoint->example_request ?: $endpoint->request_body) . "), 'application/json')" : '') . "\n    ->{$method}('{$url}');\n\n\$data = \$response->json();";
        $snippets[] = ['language' => 'php', 'title' => 'PHP (Laravel)', 'code' => $php, 'sort_order' => 2];

        // JavaScript (Axios)
        $jsCode = $hasBody
            ? "const axios = require('axios');\n\nconst response = await axios.{$method}('{$url}', " . json_encode($endpoint->example_request ?: $endpoint->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ", {\n  headers: {\n    Authorization: 'Bearer YOUR_API_KEY',\n    Accept: 'application/json',\n  },\n});\n\nconst data = response.data;"
            : "const axios = require('axios');\n\nconst response = await axios.{$method}('{$url}', {\n  headers: {\n    Authorization: 'Bearer YOUR_API_KEY',\n    Accept: 'application/json',\n  },\n});\n\nconst data = response.data;";
        $snippets[] = ['language' => 'javascript', 'title' => 'JavaScript (Axios)', 'code' => $jsCode, 'sort_order' => 3];

        // Python
        $pyBody = $hasBody ? ",\n    json=" . str_replace("'", '"', json_encode($endpoint->example_request ?: $endpoint->request_body, JSON_UNESCAPED_UNICODE)) : '';
        $py = "import requests\n\nheaders = {\n    'Authorization': 'Bearer YOUR_API_KEY',\n    'Accept': 'application/json',\n}\n\nresponse = requests.{$method}('{$url}'{$pyBody})\ndata = response.json()";
        $snippets[] = ['language' => 'python', 'title' => 'Python (requests)', 'code' => $py, 'sort_order' => 4];

        // Go
        $goBody = $hasBody ? "payload := []byte(`" . json_encode($endpoint->example_request ?: $endpoint->request_body) . "`)\nreq, _ := http.NewRequest(\"STRTOUPPER{$method}\", \"{$url}\", bytes.NewBuffer(payload))" : "req, _ := http.NewRequest(\"STRTOUPPER{$method}\", \"{$url}\", nil)";
        $go = str_replace('STRTOUPPER', strtoupper($method), "package main\n\nimport (\n\t\"bytes\"\n\t\"encoding/json\"\n\t\"fmt\"\n\t\"io\"\n\t\"net/http\"\n)\n\nfunc main() {\n\t{$goBody}\n\treq.Header.Set(\"Authorization\", \"Bearer YOUR_API_KEY\")\n\treq.Header.Set(\"Accept\", \"application/json\")\n\n\tclient := &http.Client{}\n\tresp, err := client.Do(req)\n\tif err != nil {\n\t\tpanic(err)\n\t}\n\tdefer resp.Body.Close()\n\n\tbody, _ := io.ReadAll(resp.Body)\n\tfmt.Println(string(body))\n}");
        $snippets[] = ['language' => 'go', 'title' => 'Go', 'code' => $go, 'sort_order' => 5];

        return $snippets;
    }

    // ─── OpenAPI 辅助方法 ───

    protected function buildOpenApiSchemas(): array
    {
        $schemas = ApiDocSchema::all();
        $result = [];

        foreach ($schemas as $schema) {
            $result[$schema->name] = [
                'type' => $schema->type ?? 'object',
                'description' => $schema->description ?? '',
                'properties' => $schema->properties ?? (object) [],
            ];
            if ($schema->example) {
                $result[$schema->name]['example'] = $schema->example;
            }
        }

        return $result;
    }

    protected function buildOpenApiTags($endpoints): array
    {
        $groups = $endpoints->pluck('group')->unique();
        $tags = [];

        foreach ($groups as $group) {
            if ($group) {
                $tags[] = [
                    'name' => $this->getGroups()[$group] ?? $group,
                    'description' => "{$group} 相关接口",
                ];
            }
        }

        return $tags;
    }

    protected function buildOpenApiParameters(?array $parameters): array
    {
        if (empty($parameters)) return [];

        $result = [];
        foreach ($parameters as $param) {
            $result[] = [
                'name' => $param['name'] ?? 'param',
                'in' => $param['in'] ?? 'query',
                'description' => $param['description'] ?? '',
                'required' => $param['required'] ?? false,
                'schema' => [
                    'type' => $param['type'] ?? 'string',
                ],
            ];
        }

        return $result;
    }

    protected function buildOpenApiResponses(?array $responses): array
    {
        if (empty($responses)) {
            return [
                '200' => ['description' => 'Successful response'],
                '400' => ['description' => 'Bad request'],
                '401' => ['description' => 'Unauthorized'],
                '403' => ['description' => 'Forbidden'],
                '404' => ['description' => 'Not found'],
                '422' => ['description' => 'Validation error'],
                '500' => ['description' => 'Internal server error'],
            ];
        }

        $result = [];
        foreach ($responses as $code => $resp) {
            $result[(string) $code] = [
                'description' => $resp['description'] ?? 'Response',
                'content' => [
                    'application/json' => [
                        'schema' => $resp['schema'] ?? ['type' => 'object'],
                    ],
                ],
            ];
        }

        return $result;
    }

    // ─── 辅助方法 ───

    protected function parseResponseBody(string $body): array|string
    {
        $decoded = json_decode($body, true);
        return $decoded !== null ? $decoded : $body;
    }

    protected function pathToFunction(string $path, string $method): string
    {
        // /api/admin/licenses -> listLicenses
        // /api/admin/licenses/{id} -> getLicense
        $parts = explode('/', trim($path, '/'));
        $parts = array_filter($parts, fn($p) => !str_contains($p, '{') && !in_array($p, ['api', 'admin']));
        $parts = array_values($parts);
        $name = implode('_', $parts);
        $prefix = match (strtolower($method)) {
            'get' => 'get',
            'post' => 'create',
            'put', 'patch' => 'update',
            'delete' => 'delete',
            default => 'call',
        };
        return Str::camel($prefix . '_' . $name);
    }

    protected function extractParamNames(?array $params): array
    {
        if (empty($params)) return [];
        return array_map(fn($p) => $p['name'] ?? 'param', $params);
    }

    protected function methodToPython(string $method): string
    {
        return match ($method) {
            'get' => 'get',
            'post' => 'post',
            'put' => 'put',
            'patch' => 'patch',
            'delete' => 'delete',
            default => 'get',
        };
    }

    protected function methodToJs(string $method): string
    {
        return match ($method) {
            'get' => 'get',
            'post' => 'post',
            'put' => 'put',
            'patch' => 'patch',
            'delete' => 'delete',
            default => 'get',
        };
    }

    // ═══════════════ API Changelog 自动生成 (M3-32) ═══════════════

    /**
     * 对指定版本创建端点快照
     */
    public function createSnapshot(int $apiVersionId, string $versionLabel): int
    {
        $endpoints = ApiDocEndpoint::where('api_version_id', $apiVersionId)->get();
        $snapshotAt = now();
        $count = 0;

        foreach ($endpoints as $ep) {
            ApiEndpointSnapshot::create([
                'api_version_id' => $apiVersionId,
                'endpoint_id' => $ep->id,
                'method' => $ep->method,
                'path' => $ep->path,
                'group' => $ep->group,
                'tag' => $ep->tag,
                'summary' => $ep->summary,
                'status' => $ep->status ?? 'active',
                'parameters_snapshot' => $ep->parameters,
                'responses_snapshot' => $ep->responses,
                'snapshot_version' => $versionLabel,
                'snapshot_at' => $snapshotAt,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 自动检测端点变更并生成 Changelog
     *
     * 对比当前端点与上次快照，自动生成新增/修改/弃用/移除的变更日志
     */
    public function autoGenerateChangelog(int $apiVersionId): array
    {
        $version = \App\Models\ApiVersion::findOrFail($apiVersionId);
        $versionLabel = $version->version;
        $lastSnapshot = $this->getLatestSnapshotVersion($apiVersionId);

        if (!$lastSnapshot) {
            // 无快照：首次创建全量快照
            $count = $this->createSnapshot($apiVersionId, $versionLabel);
            return [
                'status' => 'snapshot_created',
                'message' => "已创建首个端点快照，共 {$count} 个端点",
                'changelogs_created' => 0,
                'changes' => [],
            ];
        }

        // 获取上次快照的端点集合
        $snapshots = ApiEndpointSnapshot::where('api_version_id', $apiVersionId)
            ->where('snapshot_version', $lastSnapshot)
            ->get()
            ->keyBy(fn($s) => $s->method . ' ' . $s->path);

        // 获取当前端点集合
        $current = ApiDocEndpoint::where('api_version_id', $apiVersionId)
            ->get()
            ->keyBy(fn($e) => $e->method . ' ' . $e->path);

        // 对比差异
        $added = [];
        $removed = [];
        $changed = [];
        $deprecated = [];
        $reactivated = [];

        $allKeys = $snapshots->keys()->merge($current->keys())->unique()->sort();

        foreach ($allKeys as $key) {
            $old = $snapshots->get($key);
            $new = $current->get($key);

            if ($old && !$new) {
                // 端点被删除
                $removed[] = [
                    'key' => $key,
                    'method' => $old->method,
                    'path' => $old->path,
                    'summary' => $old->summary,
                    'old_status' => $old->status,
                ];
            } elseif (!$old && $new) {
                // 新增端点
                $added[] = [
                    'key' => $key,
                    'method' => $new->method,
                    'path' => $new->path,
                    'summary' => $new->summary,
                    'status' => $new->status ?? 'active',
                ];
            } elseif ($old && $new) {
                // 端点存在但可能有变化
                $changes = $this->detectEndpointChanges($old, $new);

                // 状态变更
                if ($old->status !== $new->status) {
                    if ($new->status === 'deprecated') {
                        $deprecated[] = [
                            'key' => $key,
                            'method' => $new->method,
                            'path' => $new->path,
                            'summary' => $new->summary,
                            'old_status' => $old->status,
                            'new_status' => $new->status,
                            'changes' => $changes,
                        ];
                    } elseif ($old->status === 'deprecated' && in_array($new->status, ['active', 'beta'])) {
                        $reactivated[] = [
                            'key' => $key,
                            'method' => $new->method,
                            'path' => $new->path,
                            'summary' => $new->summary,
                            'old_status' => $old->status,
                            'new_status' => $new->status,
                        ];
                    }
                }

                if (!empty($changes)) {
                    $changed[] = [
                        'key' => $key,
                        'method' => $new->method,
                        'path' => $new->path,
                        'summary' => $new->summary,
                        'changes' => $changes,
                    ];
                }
            }
        }

        // 生成 Changelog
        $changelogsCreated = 0;

        // 1. 新增变更日志
        if (!empty($added)) {
            $this->createChangelogFromDiff($versionLabel, 'new', '新增 API 端点', $added, $apiVersionId);
            $changelogsCreated++;
        }

        // 2. 修改变更日志
        if (!empty($changed)) {
            $this->createChangelogFromDiff($versionLabel, 'update', 'API 端点更新', $changed, $apiVersionId);
            $changelogsCreated++;
        }

        // 3. 弃用变更日志
        if (!empty($deprecated)) {
            $this->createChangelogFromDiff($versionLabel, 'deprecation', 'API 端点废弃', $deprecated, $apiVersionId);
            $changelogsCreated++;
        }

        // 4. 移除变更日志
        if (!empty($removed)) {
            $this->createChangelogFromDiff($versionLabel, 'removal', 'API 端点移除', $removed, $apiVersionId);
            $changelogsCreated++;
        }

        // 5. 重新激活
        if (!empty($reactivated)) {
            $this->createChangelogFromDiff($versionLabel, 'update', 'API 端点重新激活', $reactivated, $apiVersionId);
            $changelogsCreated++;
        }

        // 创建新的快照
        $this->createSnapshot($apiVersionId, $versionLabel);

        return [
            'status' => 'completed',
            'message' => "自动检测完成",
            'changelogs_created' => $changelogsCreated,
            'changes' => [
                'added' => count($added),
                'changed' => count($changed),
                'deprecated' => count($deprecated),
                'removed' => count($removed),
                'reactivated' => count($reactivated),
            ],
            'added' => $added,
            'changed' => $changed,
            'deprecated' => $deprecated,
            'removed' => $removed,
            'reactivated' => $reactivated,
        ];
    }

    /**
     * 获取上次快照版本
     */
    protected function getLatestSnapshotVersion(int $apiVersionId): ?string
    {
        $latest = ApiEndpointSnapshot::where('api_version_id', $apiVersionId)
            ->orderByDesc('snapshot_at')
            ->first();

        return $latest?->snapshot_version;
    }

    /**
     * 检测单个端点的变更
     */
    protected function detectEndpointChanges(ApiEndpointSnapshot $snapshot, ApiDocEndpoint $current): array
    {
        $changes = [];

        // 检查描述/摘要变更
        if ($snapshot->summary !== $current->summary) {
            $changes[] = [
                'field' => 'summary',
                'type' => 'modified',
                'old' => $snapshot->summary,
                'new' => $current->summary,
            ];
        }

        // 检查参数变更
        $oldParams = $snapshot->parameters_snapshot ?: [];
        $newParams = $current->parameters ?: [];

        if ($this->isJsonChanged($oldParams, $newParams)) {
            $oldCount = count($oldParams);
            $newCount = count($newParams);
            $diff = abs($newCount - $oldCount);
            if ($newCount > $oldCount) {
                $changes[] = ['field' => 'parameters', 'type' => 'added', 'detail' => "新增 {$diff} 个参数"];
            } elseif ($oldCount > $newCount) {
                $changes[] = ['field' => 'parameters', 'type' => 'removed', 'detail' => "移除 {$diff} 个参数"];
            } else {
                $changes[] = ['field' => 'parameters', 'type' => 'modified', 'detail' => '参数已更新'];
            }
        }

        // 检查响应变更
        $oldResp = $snapshot->responses_snapshot ?: [];
        $newResp = $current->responses ?: [];

        if ($this->isJsonChanged($oldResp, $newResp)) {
            $changes[] = ['field' => 'responses', 'type' => 'modified', 'detail' => '响应结构已更新'];
        }

        // 检查分组/标签变更
        if ($snapshot->group !== $current->group) {
            $changes[] = [
                'field' => 'group',
                'type' => 'modified',
                'old' => $snapshot->group,
                'new' => $current->group,
            ];
        }

        if ($snapshot->tag !== $current->tag) {
            $changes[] = [
                'field' => 'tag',
                'type' => 'modified',
                'old' => $snapshot->tag,
                'new' => $current->tag,
            ];
        }

        return $changes;
    }

    /**
     * 从 Diff 结果生成 Changelog
     */
    protected function createChangelogFromDiff(string $version, string $type, string $titlePrefix, array $items, int $apiVersionId): ApiChangelog
    {
        $endpointRefs = array_map(fn($item) => $item['key'] ?? ($item['method'] . ' ' . $item['path']), $items);
        $count = count($items);

        $description = "{$titlePrefix}：共 {$count} 个端点\n\n";
        foreach ($items as $item) {
            $description .= "- {$item['method']} {$item['path']}";
            if (!empty($item['summary'])) {
                $description .= " ({$item['summary']})";
            }
            if (!empty($item['changes'])) {
                $changeDesc = implode('; ', array_map(fn($c) => $c['field'] . ': ' . ($c['detail'] ?? $c['type']), array_slice($item['changes'], 0, 3)));
                $description .= " → {$changeDesc}";
            }
            $description .= "\n";
        }

        return ApiChangelog::create([
            'version' => $version,
            'release_date' => now(),
            'type' => $type,
            'title' => "{$titlePrefix} (v{$version})",
            'description' => $description,
            'affected_endpoints' => $endpointRefs,
            'source' => 'auto_detect',
            'migration_guide' => null,
        ]);
    }

    /**
     * 简易 JSON 变更检查
     */
    protected function isJsonChanged(array $old, array $new): bool
    {
        return json_encode($old) !== json_encode($new);
    }

    /**
     * 获取自动检测记录
     */
    public function getAutoDetectionHistory(int $limit = 20): array
    {
        return ApiChangelog::where('source', 'auto_detect')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}