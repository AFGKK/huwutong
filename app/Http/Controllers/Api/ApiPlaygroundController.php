<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class ApiPlaygroundController extends Controller
{
    /**
     * 获取 Playground 可用的 API 端点列表
     */
    public function endpoints(): JsonResponse
    {
        $endpoints = [
            // License
            [
                'id' => 'license_activate',
                'group' => 'License',
                'method' => 'POST',
                'path' => '/api/license/activate',
                'title' => '激活 License',
                'description' => '使用 License Key 和设备指纹激活许可证',
                'auth' => false,
                'request_body' => [
                    ['name' => 'license_key', 'type' => 'string', 'required' => true, 'description' => 'License Key'],
                    ['name' => 'fingerprint', 'type' => 'string', 'required' => true, 'description' => '设备指纹 (SHA256)'],
                    ['name' => 'components', 'type' => 'object', 'required' => true, 'description' => '设备组件信息，包含 hostname/os/os_version 等'],
                ],
                'response_example' => '{"success":true,"data":{"license_key":"HWT-XXXX","status":"active"}}',
            ],
            [
                'id' => 'license_validate',
                'group' => 'License',
                'method' => 'POST',
                'path' => '/api/license/validate',
                'title' => '验证 License',
                'description' => '验证 License 是否有效并返回详细信息',
                'auth' => false,
                'request_body' => [
                    ['name' => 'license_key', 'type' => 'string', 'required' => true, 'description' => '要验证的 License Key'],
                ],
            ],
            [
                'id' => 'license_list',
                'group' => 'License',
                'method' => 'GET',
                'path' => '/api/licenses',
                'title' => 'License 列表',
                'description' => '获取当前租户下的所有 License（需认证）',
                'auth' => true,
                'query_params' => [
                    ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => '每页数量（默认 20）'],
                    ['name' => 'search', 'type' => 'string', 'required' => false, 'description' => '搜索关键字'],
                    ['name' => 'sort', 'type' => 'string', 'required' => false, 'description' => '排序字段，如 -created_at'],
                ],
            ],
            [
                'id' => 'license_create',
                'group' => 'License',
                'method' => 'POST',
                'path' => '/api/licenses',
                'title' => '创建 License',
                'description' => '创建新的许可证',
                'auth' => true,
                'request_body' => [
                    ['name' => 'product_id', 'type' => 'integer', 'required' => true, 'description' => '产品 ID'],
                    ['name' => 'customer_id', 'type' => 'integer', 'required' => true, 'description' => '客户 ID'],
                    ['name' => 'type', 'type' => 'string', 'required' => true, 'description' => '类型: standard/enterprise/trial'],
                    ['name' => 'expires_at', 'type' => 'string', 'required' => false, 'description' => '过期时间 (Y-m-d)'],
                ],
            ],

            // 产品
            [
                'id' => 'product_list',
                'group' => '产品',
                'method' => 'GET',
                'path' => '/api/products',
                'title' => '产品列表',
                'description' => '获取所有产品',
                'auth' => true,
            ],
            [
                'id' => 'product_stats',
                'group' => '产品',
                'method' => 'GET',
                'path' => '/api/products/stats',
                'title' => '产品统计',
                'description' => '获取产品统计数据',
                'auth' => true,
            ],

            // 客户
            [
                'id' => 'customer_list',
                'group' => '客户',
                'method' => 'GET',
                'path' => '/api/customers',
                'title' => '客户列表',
                'description' => '获取所有客户',
                'auth' => true,
            ],
            [
                'id' => 'customer_stats',
                'group' => '客户',
                'method' => 'GET',
                'path' => '/api/customers/stats',
                'title' => '客户统计',
                'description' => '获取客户统计数据',
                'auth' => true,
            ],

            // 设备
            [
                'id' => 'device_list',
                'group' => '设备',
                'method' => 'GET',
                'path' => '/api/devices',
                'title' => '设备列表',
                'description' => '获取所有设备',
                'auth' => true,
            ],
            [
                'id' => 'device_stats',
                'group' => '设备',
                'method' => 'GET',
                'path' => '/api/devices/stats',
                'title' => '设备统计',
                'description' => '获取设备统计数据',
                'auth' => true,
            ],

            // 健康检查
            [
                'id' => 'health_live',
                'group' => '系统',
                'method' => 'GET',
                'path' => '/api/health/live',
                'title' => '健康检查（存活）',
                'description' => 'API 存活检查',
                'auth' => false,
            ],
            [
                'id' => 'health_ready',
                'group' => '系统',
                'method' => 'GET',
                'path' => '/api/health/ready',
                'title' => '健康检查（就绪）',
                'description' => 'API 就绪检查',
                'auth' => false,
            ],
            [
                'id' => 'health_status',
                'group' => '系统',
                'method' => 'GET',
                'path' => '/api/health/status',
                'title' => '系统状态',
                'description' => '获取系统详细状态',
                'auth' => false,
            ],

            // 认证
            [
                'id' => 'auth_user',
                'group' => '认证',
                'method' => 'GET',
                'path' => '/api/user',
                'title' => '当前用户信息',
                'description' => '获取当前登录用户信息（需认证）',
                'auth' => true,
            ],
            [
                'id' => 'auth_login',
                'group' => '认证',
                'method' => 'POST',
                'path' => '/api/login',
                'title' => '登录',
                'description' => '使用邮箱+密码登录',
                'auth' => false,
                'request_body' => [
                    ['name' => 'email', 'type' => 'string', 'required' => true, 'description' => '邮箱'],
                    ['name' => 'password', 'type' => 'string', 'required' => true, 'description' => '密码'],
                ],
            ],

            // 用户权限
            [
                'id' => 'permissions_mine',
                'group' => '权限',
                'method' => 'GET',
                'path' => '/api/permissions/mine',
                'title' => '我的权限',
                'description' => '获取当前用户的角色和权限列表',
                'auth' => true,
            ],
            [
                'id' => 'roles_list',
                'group' => '权限',
                'method' => 'GET',
                'path' => '/api/roles',
                'title' => '角色列表',
                'description' => '获取所有角色',
                'auth' => true,
            ],

            // Feature Flag
            [
                'id' => 'feature_flags_check',
                'group' => 'Feature Flag',
                'method' => 'POST',
                'path' => '/api/license/check-feature',
                'title' => '检查 Feature Flag',
                'description' => '检查 License 是否拥有某个功能特性',
                'auth' => false,
                'request_body' => [
                    ['name' => 'license_key', 'type' => 'string', 'required' => true, 'description' => 'License Key'],
                    ['name' => 'feature_key', 'type' => 'string', 'required' => true, 'description' => '功能标识'],
                ],
            ],

            // Feature Flag 管理
            [
                'id' => 'feature_flags_list',
                'group' => 'Feature Flag',
                'method' => 'GET',
                'path' => '/api/feature-flags',
                'title' => 'Feature Flag 列表',
                'description' => '获取所有 Feature Flag',
                'auth' => true,
            ],

            // 审计日志
            [
                'id' => 'audit_logs',
                'group' => '系统',
                'method' => 'GET',
                'path' => '/api/audit-logs',
                'title' => '审计日志列表',
                'description' => '获取审计日志',
                'auth' => true,
            ],
            [
                'id' => 'audit_logs_stats',
                'group' => '系统',
                'method' => 'GET',
                'path' => '/api/audit-logs/stats',
                'title' => '审计日志统计',
                'description' => '获取审计日志统计',
                'auth' => true,
            ],

            // 通知
            [
                'id' => 'notifications_list',
                'group' => '通知',
                'method' => 'GET',
                'path' => '/api/notifications',
                'title' => '通知列表',
                'description' => '获取我的通知列表',
                'auth' => true,
            ],
            [
                'id' => 'notifications_unread',
                'group' => '通知',
                'method' => 'GET',
                'path' => '/api/notifications/unread-count',
                'title' => '未读通知数',
                'description' => '获取未读通知数量',
                'auth' => true,
            ],
        ];

        return ApiResponse::success($endpoints);
    }

    /**
     * 执行 API 请求（代理到本地）
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'required|in:GET,POST,PUT,DELETE',
            'path' => 'required|string',
            'body' => 'nullable|array',
            'query' => 'nullable|array',
            'headers' => 'nullable|array',
        ]);

        $method = strtoupper($validated['method']);
        $path = $validated['path'];
        $url = url($path);

        try {
            $http = Http::timeout(15);

            // 添加认证头（如果有）
            $customHeaders = $validated['headers'] ?? [];
            if (! empty($customHeaders['Authorization'])) {
                $http->withHeaders(['Authorization' => $customHeaders['Authorization']]);
            }
            if (! empty($customHeaders['X-Nonce'])) {
                $http->withHeaders(['X-Nonce' => $customHeaders['X-Nonce']]);
            }
            if (! empty($customHeaders['Accept'])) {
                $http->withHeaders(['Accept' => $customHeaders['Accept']]);
            }

            // 添加默认 header
            $http->withHeaders(['Accept' => 'application/json']);

            // 执行请求
            $response = $http->send($method, $url, [
                'json' => $validated['body'] ?? [],
                'query' => $validated['query'] ?? [],
            ]);

            $body = $response->body();
            $decoded = json_decode($body, true);

            return ApiResponse::success([
                'status' => $response->status(),
                'headers' => $this->filterResponseHeaders($response->headers()),
                'body' => $decoded ?? $body,
                'duration_ms' => $response->handlerStats()['total_time_us'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('REQUEST_FAILED', '请求执行失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 代码生成
     */
    public function generateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|in:curl,php,node,python,java,go',
            'method' => 'required|string',
            'path' => 'required|string',
            'body' => 'nullable|array',
            'query' => 'nullable|array',
            'headers' => 'nullable|array',
        ]);

        $method = strtoupper($validated['method']);
        $path = $validated['path'];
        $url = url($path);
        $body = $validated['body'] ?? [];
        $query = $validated['query'] ?? [];
        $headers = $validated['headers'] ?? [];
        $fullUrl = $query ? $url . '?' . http_build_query($query) : $url;

        $code = match ($validated['language']) {
            'curl' => $this->generateCurl($method, $fullUrl, $body, $headers),
            'php' => $this->generatePhp($method, $fullUrl, $body, $headers),
            'node' => $this->generateNode($method, $fullUrl, $body, $headers),
            'python' => $this->generatePython($method, $fullUrl, $body, $headers),
            'java' => $this->generateJava($method, $fullUrl, $body, $headers),
            'go' => $this->generateGo($method, $fullUrl, $body, $headers),
            default => '',
        };

        return ApiResponse::success([
            'language' => $validated['language'],
            'code' => $code,
            'url' => $fullUrl,
            'method' => $method,
        ]);
    }

    private function generateCurl(string $method, string $url, array $body, array $headers): string
    {
        $parts = ["curl -X {$method} \"{$url}\""];
        $parts[] = "  -H \"Content-Type: application/json\"";

        foreach ($headers as $key => $val) {
            if (in_array($key, ['Content-Type', 'Accept'])) continue;
            $parts[] = "  -H \"{$key}: {$val}\"";
        }

        if ($body) {
            $json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $parts[] = "  -d '{$json}'";
        }

        return implode(" \\\n", $parts);
    }

    private function generatePhp(string $method, string $url, array $body, array $headers): string
    {
        $code = "use Illuminate\\Support\\Facades\\Http;\n\n";
        $code .= "\$response = Http::withHeaders([\n";
        $code .= "    'Content-Type' => 'application/json',\n";
        foreach ($headers as $key => $val) {
            if (in_array($key, ['Content-Type', 'Accept'])) continue;
            $code .= "    '{$key}' => '{$val}',\n";
        }
        $code .= "])";

        $methodLower = strtolower($method);
        if ($body) {
            $json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "->{$methodLower}('{$url}', {$json});\n\n";
        } elseif ($method === 'GET') {
            $code .= "->get('{$url}');\n\n";
        } else {
            $code .= "->{$methodLower}('{$url}');\n\n";
        }

        $code .= "// 处理响应\n";
        $code .= "\$data = \$response->json();\n";
        $code .= "if (\$response->successful()) {\n";
        $code .= "    // 请求成功\n";
        $code .= "    dump(\$data);\n";
        $code .= "} else {\n";
        $code .= "    // 请求失败\n";
        $code .= "    dump(\$response->status(), \$data);\n";
        $code .= "}";

        return $code;
    }

    private function generateNode(string $method, string $url, array $body, array $headers): string
    {
        $headers['Content-Type'] = 'application/json';
        $headerStr = json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $code = "const axios = require('axios');\n";
        $code .= "// ESM: import axios from 'axios';\n\n";
        $code .= "const response = await axios({\n";
        $code .= "  method: '{$method}',\n";
        $code .= "  url: '{$url}',\n";
        $code .= "  headers: {$headerStr},";

        if ($body) {
            $json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "\n  data: {$json},";
        }
        $code .= "\n});\n\n";
        $code .= "console.log('Status:', response.status);\n";
        $code .= "console.log('Data:', response.data);";

        return $code;
    }

    private function generatePython(string $method, string $url, array $body, array $headers): string
    {
        $code = "import requests\n";
        $code .= "import json\n\n";
        $code .= "url = '{$url}'\n";
        $code .= "headers = {\n";
        $code .= "    'Content-Type': 'application/json',\n";
        foreach ($headers as $key => $val) {
            if (in_array($key, ['Content-Type', 'Accept'])) continue;
            $code .= "    '{$key}': '{$val}',\n";
        }
        $code .= "}\n\n";

        $methodLower = strtolower($method);
        if ($body) {
            $json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "data = {$json}\n\n";
            $code .= "response = requests.{$methodLower}(url, headers=headers, json=data)\n";
        } else {
            $code .= "response = requests.{$methodLower}(url, headers=headers)\n";
        }

        $code .= "\nprint('Status:', response.status_code)\n";
        $code .= "print('Response:', json.dumps(response.json(), indent=2, ensure_ascii=False))";

        return $code;
    }

    private function generateJava(string $method, string $url, array $body, array $headers): string
    {
        $code = "import java.net.http.HttpClient;\n";
        $code .= "import java.net.http.HttpRequest;\n";
        $code .= "import java.net.http.HttpResponse;\n";
        $code .= "import com.google.gson.Gson;\n\n";
        $code .= "HttpClient client = HttpClient.newHttpClient();\n\n";
        $code .= "HttpRequest.Builder builder = HttpRequest.newBuilder()\n";
        $code .= "    .uri(URI.create(\"{$url}\"))\n";
        $code .= "    .header(\"Content-Type\", \"application/json\");\n\n";

        if ($body) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $code .= "String json = new Gson().toJson({$json});\n";
            $code .= "builder.method(\"{$method}\", HttpRequest.BodyPublishers.ofString(json));\n";
        } else {
            $code .= "builder.method(\"{$method}\", HttpRequest.BodyPublishers.noBody());\n";
        }

        $code .= "\nHttpResponse<String> response = client.send(builder.build(), HttpResponse.BodyHandlers.ofString());\n";
        $code .= "System.out.println(\"Status: \" + response.statusCode());\n";
        $code .= "System.out.println(\"Body: \" + response.body());";

        return $code;
    }

    private function generateGo(string $method, string $url, array $body, array $headers): string
    {
        $code = "package main\n\n";
        $code .= "import (\n";
        $code .= "    \"bytes\"\n";
        $code .= "    \"encoding/json\"\n";
        $code .= "    \"fmt\"\n";
        $code .= "    \"net/http\"\n";
        $code .= ")\n\n";
        $code .= "func main() {\n";

        if ($body) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $code .= "    data := {$json}\n";
            $code .= "    jsonData, _ := json.Marshal(data)\n";
            $code .= "    req, _ := http.NewRequest(\"{$method}\", \"{$url}\", bytes.NewBuffer(jsonData))\n";
        } else {
            $code .= "    req, _ := http.NewRequest(\"{$method}\", \"{$url}\", nil)\n";
        }

        $code .= "    req.Header.Set(\"Content-Type\", \"application/json\")\n";
        foreach ($headers as $key => $val) {
            if (in_array($key, ['Content-Type', 'Accept'])) continue;
            $code .= "    req.Header.Set(\"{$key}\", \"{$val}\")\n";
        }

        $code .= "\n    client := &http.Client{}\n";
        $code .= "    resp, err := client.Do(req)\n";
        $code .= "    if err != nil {\n";
        $code .= "        panic(err)\n";
        $code .= "    }\n";
        $code .= "    defer resp.Body.Close()\n\n";
        $code .= "    fmt.Println(\"Status:\", resp.StatusCode)\n";
        $code .= "    // var result map[string]interface{}\n";
        $code .= "    // json.NewDecoder(resp.Body).Decode(&result)\n";
        $code .= "    // fmt.Printf(\"Response: %+v\\n\", result)\n";
        $code .= "}";

        return $code;
    }

    private function filterResponseHeaders(array $headers): array
    {
        $allowed = ['content-type', 'content-length', 'x-request-id', 'x-ratelimit-remaining'];
        return array_intersect_key($headers, array_flip($allowed));
    }
}
