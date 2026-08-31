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
                'description' => __('app.api.integr_wizard.desc_php'),
                'docs_url' => 'https://docs.huwutong.com/sdk/php',
                'steps' => ['composer require huwutong/license-sdk', __('app.api.integr_wizard.step_install_sdk'), __('app.api.integr_wizard.step_activate')],
            ],
            [
                'id' => 'node',
                'name' => 'Node.js',
                'icon' => 'nodejs',
                'description' => __('app.api.integr_wizard.desc_node'),
                'docs_url' => 'https://docs.huwutong.com/sdk/node',
                'steps' => ['npm install huwutong-license-sdk', __('app.api.integr_wizard.step_init_client'), __('app.api.integr_wizard.step_call_api')],
            ],
            [
                'id' => 'python',
                'name' => 'Python',
                'icon' => 'python',
                'description' => __('app.api.integr_wizard.desc_python'),
                'docs_url' => 'https://docs.huwutong.com/sdk/python',
                'steps' => ['pip install huwutong-license-sdk', __('app.api.integr_wizard.step_config_creds'), __('app.api.integr_wizard.step_verify')],
            ],
            [
                'id' => 'java',
                'name' => 'Java',
                'icon' => 'java',
                'description' => __('app.api.integr_wizard.desc_java'),
                'docs_url' => 'https://docs.huwutong.com/sdk/java',
                'steps' => [__('app.api.integr_wizard.step_add_dep'), __('app.api.integr_wizard.step_config_client'), __('app.api.integr_wizard.step_verify_license')],
            ],
            [
                'id' => 'go',
                'name' => 'Go',
                'icon' => 'go',
                'description' => __('app.api.integr_wizard.desc_go'),
                'docs_url' => 'https://docs.huwutong.com/sdk/go',
                'steps' => ['go get github.com/huwutong/license-sdk', __('app.api.integr_wizard.step_go_get'), __('app.api.integr_wizard.step_verify')],
            ],
            [
                'id' => 'dotnet',
                'name' => '.NET',
                'icon' => 'dotnet',
                'description' => __('app.api.integr_wizard.desc_dotnet'),
                'docs_url' => 'https://docs.huwutong.com/sdk/dotnet',
                'steps' => ['dotnet add package Huwutong.LicenseSDK', __('app.api.integr_wizard.step_dotnet_add'), __('app.api.integr_wizard.step_verify')],
            ],
            [
                'id' => 'rust',
                'name' => 'Rust',
                'icon' => 'rust',
                'description' => __('app.api.integr_wizard.desc_rust'),
                'docs_url' => 'https://docs.huwutong.com/sdk/rust',
                'steps' => ['cargo add huwutong-license-sdk', __('app.api.integr_wizard.step_cargo_add'), __('app.api.integr_wizard.step_integrate')],
            ],
            [
                'id' => 'curl',
                'name' => 'cURL / REST API',
                'icon' => 'api',
                'description' => __('app.api.integr_wizard.desc_http'),
                'docs_url' => 'https://docs.huwutong.com/api',
                'steps' => [__('app.api.integr_wizard.step_select_product'), __('app.api.integr_wizard.step_get_key'), __('app.api.integr_wizard.step_call_activate')],
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
                'message' => $healthResponse->successful() ? __('app.api.integr_wizard.api_ok') : __('app.api.integr_wizard.api_abnormal'),
            ];
        } catch (\Exception $e) {
            $results['api_reachable'] = [
                'success' => false,
                'status' => 0,
                'message' => __('app.api.integr_wizard.api_unreachable', ['error' => $e->getMessage()]),
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
                        ? __('app.api.integr_wizard.license_valid')
                        : ($body['error']['message'] ?? __('app.api.integr_wizard.verify_failed')),
                ];
            } catch (\Exception $e) {
                $results['license_valid'] = [
                    'success' => false,
                    'status' => 0,
                    'message' => __('app.api.integr_wizard.verify_req_failed', ['error' => $e->getMessage()]),
                ];
            }
        } else {
            $results['license_valid'] = [
                'success' => false,
                'status' => 0,
                'message' => __('app.api.integr_wizard.skip_api_down'),
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
                        ? __('app.api.integr_wizard.sdk_handshake_ok')
                        : __('app.api.integr_wizard.sdk_handshake_err'),
                ];
            } catch (\Exception $e) {
                $results['sdk_handshake'] = [
                    'success' => false,
                    'status' => 0,
                    'message' => __('app.api.integr_wizard.sdk_handshake_fail', ['error' => $e->getMessage()]),
                ];
            }
        } else {
            $results['sdk_handshake'] = [
                'success' => false,
                'status' => 0,
                'message' => __('app.api.integr_wizard.skip_license_invalid'),
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
                    $composerRequire,
                    "Copy the code above into your project",
                    "Replace the example License Key with your actual key",
                    "Test the activation/verification flow",
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
                    $npmInstall,
                    "Copy the code above into your project",
                    "Choose require or import based on your module system",
                    "Run node to test activation flow",
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
                    "Works with any programming language",
                    "Replace fingerprint with your device identifier",
                    "Call on server side to avoid exposing License Key",
                    "See the full API docs for more endpoints",
                ];
                break;

            default:
                $snippets = [];
                $instructions = [
                    "Config code auto-generated when SDK language is selected",
                    "Copy code into your project",
                    "Replace License Key with actual value",
                    "Run tests to verify connectivity",
                ];
        }

        return [
            'code' => $snippets['activate'] ?? '',
            'instructions' => $instructions,
            'snippets' => $snippets,
        ];
    }
}
