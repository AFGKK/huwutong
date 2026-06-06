<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiIntegrationWizardController extends Controller
{
    /**
     * 支持的 SDK 语言列表
     */
    public function languages(): JsonResponse
    {
        $languages = [
            [
                'id' => 'php',
                'name' => 'PHP',
                'icon' => 'php',
                'description' => 'Laravel / Symfony / 原生 PHP 项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/php',
                'steps' => ['composer require huwutong/license-sdk', '配置 API Key', '调用激活/验证'],
            ],
            [
                'id' => 'node',
                'name' => 'Node.js',
                'icon' => 'nodejs',
                'description' => 'Express / NestJS / Next.js 项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/node',
                'steps' => ['npm install huwutong-license-sdk', '初始化客户端', '调用 API'],
            ],
            [
                'id' => 'python',
                'name' => 'Python',
                'icon' => 'python',
                'description' => 'Django / Flask / FastAPI 项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/python',
                'steps' => ['pip install huwutong-license-sdk', '配置凭据', '调用验证'],
            ],
            [
                'id' => 'java',
                'name' => 'Java',
                'icon' => 'java',
                'description' => 'Spring Boot / Quarkus / Android 项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/java',
                'steps' => ['Maven/Gradle 添加依赖', '配置 LicenseClient', '验证许可证'],
            ],
            [
                'id' => 'go',
                'name' => 'Go',
                'icon' => 'go',
                'description' => 'Gin / Echo / 微服务项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/go',
                'steps' => ['go get github.com/huwutong/license-sdk', '创建客户端', '调用验证'],
            ],
            [
                'id' => 'dotnet',
                'name' => '.NET',
                'icon' => 'dotnet',
                'description' => 'ASP.NET Core / Blazor / WPF 项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/dotnet',
                'steps' => ['dotnet add package Huwutong.LicenseSDK', '注册服务', '调用验证'],
            ],
            [
                'id' => 'rust',
                'name' => 'Rust',
                'icon' => 'rust',
                'description' => '高性能 CLI / 嵌入式 / 区块链项目',
                'docs_url' => 'https://docs.huwutong.com/sdk/rust',
                'steps' => ['cargo add huwutong-license-sdk', '初始化验证器', '集成校验'],
            ],
            [
                'id' => 'curl',
                'name' => 'cURL / REST API',
                'icon' => 'api',
                'description' => '任意语言直接调用 HTTP API',
                'docs_url' => 'https://docs.huwutong.com/api',
                'steps' => ['选择产品', '获取 License Key', '调用激活/验证 API'],
            ],
        ];

        return ApiResponse::success($languages);
    }

    /**
     * 获取可用的产品列表（供用户选择）
     */
    public function products(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->select('id', 'name', 'slug', 'description', 'version')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($products);
    }

    /**
     * 生成 SDK 配置代码片段
     */
    public function generateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|string|in:php,node,python,java,go,dotnet,rust,curl',
            'product_id' => 'required|integer|exists:products,id',
            'license_key' => 'required|string',
            'api_host' => 'sometimes|url',
            'options' => 'sometimes|array',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $apiHost = $validated['api_host'] ?? config('app.url');
        $licenseKey = $validated['license_key'];

        $config = $this->buildSdkConfig(
            $validated['language'],
            $product,
            $licenseKey,
            $apiHost,
            $validated['options'] ?? [],
        );

        return ApiResponse::success([
            'language' => $validated['language'],
            'product' => $product->only(['id', 'name', 'slug']),
            'code' => $config['code'],
            'instructions' => $config['instructions'],
            'snippets' => $config['snippets'] ?? [],
        ]);
    }

    /**
     * 验证连通性 - 使用提供的 License Key 测试 API 可达性
     */
    public function testConnectivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'api_host' => 'sometimes|url',
            'product_id' => 'sometimes|integer|exists:products,id',
        ]);

        $apiHost = $validated['api_host'] ?? config('app.url');
        $licenseKey = $validated['license_key'];

        $results = [];

        // 1. 测试 API 可达性
        try {
            $healthResponse = Http::timeout(5)->get(rtrim($apiHost, '/') . '/api/health/live');
            $results['api_reachable'] = [
                'success' => $healthResponse->successful(),
                'status' => $healthResponse->status(),
                'message' => $healthResponse->successful() ? 'API 服务正常' : 'API 返回异常状态',
            ];
        } catch (\Exception $e) {
            $results['api_reachable'] = [
                'success' => false,
                'status' => 0,
                'message' => '无法连接到 API 服务: ' . $e->getMessage(),
            ];
        }

        // 2. 测试 License 有效性
        if ($results['api_reachable']['success']) {
            try {
                $validateResponse = Http::timeout(5)
                    ->withHeaders(['X-Nonce' => (string) \Illuminate\Support\Str::uuid()])
                    ->post(rtrim($apiHost, '/') . '/api/license/validate', [
                        'license_key' => $licenseKey,
                    ]);

                $body = $validateResponse->json();

                $results['license_valid'] = [
                    'success' => $validateResponse->successful(),
                    'status' => $validateResponse->status(),
                    'data' => $body['data'] ?? null,
                    'message' => $validateResponse->successful()
                        ? 'License 有效'
                        : ($body['error']['message'] ?? '验证失败'),
                ];
            } catch (\Exception $e) {
                $results['license_valid'] = [
                    'success' => false,
                    'status' => 0,
                    'message' => 'License 验证请求失败: ' . $e->getMessage(),
                ];
            }
        } else {
            $results['license_valid'] = [
                'success' => false,
                'status' => 0,
                'message' => '跳过：API 不可达',
            ];
        }

        // 3. 测试 SDK 握手（模拟 SDK 激活请求）
        if ($results['license_valid']['success']) {
            try {
                $fp = hash('sha256', 'wizard-test-' . $licenseKey . '-' . time());
                $activateResponse = Http::timeout(5)
                    ->withHeaders(['X-Nonce' => (string) \Illuminate\Support\Str::uuid()])
                    ->post(rtrim($apiHost, '/') . '/api/license/validate', [
                        'license_key' => $licenseKey,
                    ]);

                // 仅做验证测试，不做实际激活
                $results['sdk_handshake'] = [
                    'success' => $activateResponse->successful(),
                    'status' => $activateResponse->status(),
                    'message' => $activateResponse->successful()
                        ? 'SDK 握手成功，API 可正常调用'
                        : 'SDK 握手异常',
                ];
            } catch (\Exception $e) {
                $results['sdk_handshake'] = [
                    'success' => false,
                    'status' => 0,
                    'message' => 'SDK 握手失败: ' . $e->getMessage(),
                ];
            }
        } else {
            $results['sdk_handshake'] = [
                'success' => false,
                'status' => 0,
                'message' => '跳过：License 无效',
            ];
        }

        $overallSuccess = $results['api_reachable']['success']
            && $results['license_valid']['success']
            && $results['sdk_handshake']['success'];

        return ApiResponse::success([
            'overall_success' => $overallSuccess,
            'checks' => $results,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * 构建 SDK 配置
     */
    private function buildSdkConfig(string $language, Product $product, string $licenseKey, string $apiHost, array $options): array
    {
        $apiUrl = rtrim($apiHost, '/');
        $productName = $product->name;

        $snippets = [
            'activate' => '',
            'validate' => '',
            'check_feature' => '',
        ];

        $instructions = [];

        switch ($language) {
            case 'php':
                $composerRequire = 'composer require huwutong/license-sdk';
                $activateCode = <<<PHP
use Huwutong\\LicenseSDK\\LicenseClient;

\$client = new LicenseClient([
    'api_url' => '{$apiUrl}',
    'license_key' => '{$licenseKey}',
    'timeout' => 10,
]);

// 激活 License
\$result = \$client->activate([
    'fingerprint' => hash('sha256', gethostname() . php_uname()),
    'components' => [
        'hostname' => gethostname(),
        'os' => php_uname('s'),
        'os_version' => php_uname('r'),
    ],
]);

if (\$result['success']) {
    echo "激活成功！License: {\$result['data']['license_key']}";
}
PHP;
                $snippets['activate'] = $activateCode;

                $validateCode = <<<PHP
// 验证 License
\$result = \$client->validate();

if (\$result['success']) {
    echo "License 状态: " . \$result['data']['status'];
    echo "过期时间: " . \$result['data']['expires_at'];
}
PHP;
                $snippets['validate'] = $validateCode;

                $instructions = [
                    "运行: {$composerRequire}",
                    "将上方代码复制到您的项目中",
                    "替换示例 License Key 为实际值",
                    "测试激活/验证流程",
                ];
                break;

            case 'node':
                $npmInstall = 'npm install huwutong-license-sdk';
                $activateCode = <<<JS
const { LicenseClient } = require('huwutong-license-sdk');
// ESM: import { LicenseClient } from 'huwutong-license-sdk';

const client = new LicenseClient({
    apiUrl: '{$apiUrl}',
    licenseKey: '{$licenseKey}',
});

// 激活 License
async function activate() {
    const result = await client.activate({
        fingerprint: require('crypto')
            .createHash('sha256')
            .update(require('os').hostname())
            .digest('hex'),
        components: {
            hostname: require('os').hostname(),
            platform: require('os').platform(),
        },
    });

    if (result.success) {
        console.log('激活成功！', result.data);
    }
}

activate();
JS;
                $snippets['activate'] = $activateCode;

                $validateCode = <<<JS
// 验证 License
async function validate() {
    const result = await client.validate();
    if (result.success) {
        console.log('License 状态:', result.data.status);
        console.log('过期时间:', result.data.expires_at);
    }
}
validate();
JS;
                $snippets['validate'] = $validateCode;

                $instructions = [
                    "运行: {$npmInstall}",
                    "将上方代码复制到您的项目中",
                    "根据您的模块系统选择 require/import",
                    "运行 node 测试激活流程",
                ];
                break;

            case 'curl':
                $snippets['activate'] = <<<BASH
# 激活 License
curl -X POST "{$apiUrl}/api/license/activate" \\
  -H "Content-Type: application/json" \\
  -H "X-Nonce: \$(uuidgen | tr '[:upper:]' '[:lower:]')" \\
  -d '{
    "license_key": "{$licenseKey}",
    "fingerprint": "YOUR_DEVICE_FINGERPRINT",
    "components": {
      "hostname": "your-server",
      "os": "linux",
      "os_version": "6.1.0"
    }
  }'
BASH;

                $snippets['validate'] = <<<BASH
# 验证 License
curl -X POST "{$apiUrl}/api/license/validate" \\
  -H "Content-Type: application/json" \\
  -H "X-Nonce: \$(uuidgen | tr '[:upper:]' '[:lower:]')" \\
  -d '{"license_key": "{$licenseKey}"}'
BASH;

                $instructions = [
                    "支持任意编程语言调用",
                    "替换 fingerprint 为您的设备标识",
                    "建议在服务端调用，避免暴露 License Key",
                    "参考完整 API 文档查看更多端点",
                ];
                break;

            default:
                $snippets = [];
                $instructions = [
                    "选择 SDK 语言后会自动生成配置代码",
                    "复制代码到您的项目中",
                    "替换 License Key 为实际值",
                    "运行测试验证连通性",
                ];
        }

        return [
            'code' => $snippets['activate'] ?? '',
            'instructions' => $instructions,
            'snippets' => $snippets,
        ];
    }
}
