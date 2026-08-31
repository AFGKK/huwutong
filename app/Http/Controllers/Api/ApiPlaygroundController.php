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
                'title' => __('app.api.api_playground.endpoint_activate_license'),
                'description' => __('app.api.api_playground.endpoint_activate_desc'),
                'auth' => false,
                'request_body' => [
                    ['name' => 'license_key', 'type' => 'string', 'required' => true, 'description' => 'License Key'],
                    ['name' => 'fingerprint', 'type' => 'string', 'required' => true, 'description' => __('app.api.api_playground.param_fingerprint')],
                    ['name' => 'components', 'type' => 'object', 'required' => true, 'description' => __('app.api.api_playground.param_components')],
                ],
                'response_example' => '{"success":true,"data":{"license_key":"HWT-XXXX","status":"active"}}',
            ],
            [
                'id' => 'license_validate',
                'group' => 'License',
                'method' => 'POST',
                'path' => '/api/license/validate',
                'title' => __('app.api.api_playground.endpoint_verify'),
                'description' => __('app.api.api_playground.endpoint_verify_desc'),
                'auth' => false,
                'request_body' => [
                    ['name' => 'license_key', 'type' => 'string', 'required' => true, 'description' => __('app.api.api_playground.param_license_key')],
                ],
            ],
            [
                'id' => 'license_list',
                'group' => 'License',
                'method' => 'GET',
                'path' => '/api/licenses',
                'title' => __('app.api.api_playground.endpoint_license_list'),
                'description' => __('app.api.api_playground.endpoint_license_list_desc'),
                'auth' => true,
                'query_params' => [
                    ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => __('app.api.api_playground.param_per_page')],
                    ['name' => 'search', 'type' => 'string', 'required' => false, 'description' => __('app.api.api_playground.param_search')],
                    ['name' => 'sort', 'type' => 'string', 'required' => false, 'description' => __('app.api.api_playground.param_sort')],
                ],
            ],
            [
                'id' => 'license_create',
                'group' => 'License',
                'method' => 'POST',
                'path' => '/api/licenses',
                'title' => __('app.api.api_playground.endpoint_create_license'),
                'description' => __('app.api.api_playground.endpoint_create_license_desc'),
                'auth' => true,
                'request_body' => [
                    ['name' => 'product_id', 'type' => 'integer', 'required' => true, 'description' => __('app.api.api_playground.param_product_id')],
                    ['name' => 'customer_id', 'type' => 'integer', 'required' => true, 'description' => __('app.api.api_playground.param_customer_id')],
                    ['name' => 'type', 'type' => 'string', 'required' => true, 'description' => __('app.api.api_playground.param_type')],
                    ['name' => 'expires_at', 'type' => 'string', 'required' => false, 'description' => __('app.api.api_playground.param_expires_at')],
                ],
            ],

            // 产品
            [
                'id' => 'product_list',
                'group' => __('app.api.api_playground.group_products'),
                'method' => 'GET',
                'path' => '/api/products',
                'title' => __('app.api.api_playground.endpoint_product_list'),
                'description' => __('app.api.api_playground.endpoint_product_list_desc'),
                'auth' => true,
            ],
            [
                'id' => 'product_stats',
                'group' => __('app.api.api_playground.group_products'),
                'method' => 'GET',
                'path' => '/api/products/stats',
                'title' => __('app.api.api_playground.endpoint_product_stats'),
                'description' => __('app.api.api_playground.endpoint_product_stats_desc'),
                'auth' => true,
            ],

            // 客户
            [
                'id' => 'customer_list',
                'group' => __('app.api.api_playground.group_customers'),
                'method' => 'GET',
                'path' => '/api/customers',
                'title' => __('app.api.api_playground.endpoint_customer_list'),
                'description' => __('app.api.api_playground.endpoint_customer_list_desc'),
                'auth' => true,
            ],
            [
                'id' => 'customer_stats',
                'group' => __('app.api.api_playground.group_customers'),
                'method' => 'GET',
                'path' => '/api/customers/stats',
                'title' => __('app.api.api_playground.endpoint_customer_stats'),
                'description' => __('app.api.api_playground.endpoint_customer_stats_desc'),
                'auth' => true,
            ],

            // 设备
            [
                'id' => 'device_list',
                'group' => __('app.api.api_playground.group_devices'),
                'method' => 'GET',
                'path' => '/api/devices',
                'title' => __('app.api.api_playground.endpoint_device_list'),
                'description' => __('app.api.api_playground.endpoint_device_list_desc'),
                'auth' => true,
            ],
            [
                'id' => 'device_stats',
                'group' => __('app.api.api_playground.group_devices'),
                'method' => 'GET',
                'path' => '/api/devices/stats',
                'title' => __('app.api.api_playground.endpoint_device_stats'),
                'description' => __('app.api.api_playground.endpoint_device_stats_desc'),
                'auth' => true,
            ],

            // 健康检查
            [
                'id' => 'health_live',
                'group' => __('app.api.api_playground.group_system'),
                'method' => 'GET',
                'path' => '/api/health/live',
                'title' => __('app.api.api_playground.endpoint_health_liveness'),
                'description' => __('app.api.api_playground.endpoint_health_liveness_desc'),
                'auth' => false,
            ],
            [
                'id' => 'health_ready',
                'group' => __('app.api.api_playground.group_system'),
                'method' => 'GET',
                'path' => '/api/health/ready',
                'title' => __('app.api.api_playground.endpoint_health_readiness'),
                'description' => __('app.api.api_playground.endpoint_health_readiness_desc'),
                'auth' => false,
            ],
            [
                'id' => 'health_status',
                'group' => __('app.api.api_playground.group_system'),
                'method' => 'GET',
                'path' => '/api/health/status',
                'title' => __('app.api.api_playground.endpoint_sys_status'),
                'description' => __('app.api.api_playground.endpoint_sys_status_desc'),
                'auth' => false,
            ],

            // 认证
            [
                'id' => 'auth_user',
                'group' => __('app.api.api_playground.group_auth'),
                'method' => 'GET',
                'path' => '/api/user',
                'title' => __('app.api.api_playground.endpoint_user_profile'),
                'description' => __('app.api.api_playground.endpoint_user_profile_desc'),
                'auth' => true,
            ],
            [
                'id' => 'auth_login',
                'group' => __('app.api.api_playground.group_auth'),
                'method' => 'POST',
                'path' => '/api/login',
                'title' => __('app.api.api_playground.endpoint_login'),
                'description' => __('app.api.api_playground.endpoint_login_desc'),
                'auth' => false,
                'request_body' => [
                    ['name' => 'email', 'type' => 'string', 'required' => true, 'description' => __('app.api.api_playground.param_email')],
                    ['name' => 'password', 'type' => 'string', 'required' => true, 'description' => __('app.api.api_playground.param_password')],
                ],
            ],

            // 用户权限
            [
                'id' => 'permissions_mine',
                'group' => __('app.api.api_playground.group_permissions'),
                'method' => 'GET',
                'path' => '/api/permissions/mine',
                'title' => __('app.api.api_playground.endpoint_my_perms'),
                'description' => __('app.api.api_playground.endpoint_my_perms_desc'),
                'auth' => true,
            ],
            [
                'id' => 'roles_list',
                'group' => __('app.api.api_playground.group_permissions'),
                'method' => 'GET',
                'path' => '/api/roles',
                'title' => __('app.api.api_playground.endpoint_role_list'),
                'description' => __('app.api.api_playground.endpoint_role_list_desc'),
                'auth' => true,
            ],

            // Feature Flag
            [
                'id' => 'feature_flags_check',
                'group' => 'Feature Flag',
                'method' => 'POST',
                'path' => '/api/license/check-feature',
                'title' => __('app.api.api_playground.endpoint_feature_flag'),
                'description' => __('app.api.api_playground.endpoint_feature_flag_desc'),
                'auth' => false,
                'request_body' => [
                    ['name' => 'license_key', 'type' => 'string', 'required' => true, 'description' => 'License Key'],
                    ['name' => 'feature_key', 'type' => 'string', 'required' => true, 'description' => __('app.api.api_playground.param_feature_key')],
                ],
            ],

            // Feature Flag 管理
            [
                'id' => 'feature_flags_list',
                'group' => 'Feature Flag',
                'method' => 'GET',
                'path' => '/api/feature-flags',
                'title' => __('app.api.api_playground.endpoint_feature_flag_list'),
                'description' => __('app.api.api_playground.endpoint_feature_flag_list_desc'),
                'auth' => true,
            ],

            // 审计日志
            [
                'id' => 'audit_logs',
                'group' => __('app.api.api_playground.group_system'),
                'method' => 'GET',
                'path' => '/api/audit-logs',
                'title' => __('app.api.api_playground.endpoint_audit_log'),
                'description' => __('app.api.api_playground.endpoint_audit_log_desc'),
                'auth' => true,
            ],
            [
                'id' => 'audit_logs_stats',
                'group' => __('app.api.api_playground.group_system'),
                'method' => 'GET',
                'path' => '/api/audit-logs/stats',
                'title' => __('app.api.api_playground.endpoint_audit_stats'),
                'description' => __('app.api.api_playground.endpoint_audit_stats_desc'),
                'auth' => true,
            ],

            // 通知
            [
                'id' => 'notifications_list',
                'group' => __('app.api.api_playground.group_notifications'),
                'method' => 'GET',
                'path' => '/api/notifications',
                'title' => __('app.api.api_playground.endpoint_notifications'),
                'description' => __('app.api.api_playground.endpoint_notifications_desc'),
                'auth' => true,
            ],
            [
                'id' => 'notifications_unread',
                'group' => __('app.api.api_playground.group_notifications'),
                'method' => 'GET',
                'path' => '/api/notifications/unread-count',
                'title' => __('app.api.api_playground.endpoint_unread_count'),
                'description' => __('app.api.api_playground.endpoint_unread_count_desc'),
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
            return ApiResponse::error('REQUEST_FAILED', __('app.api.api_playground.request_failed', ['error' => $e->getMessage()]), 500);
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
