<?php

namespace App\Services;

/**
 * SDK 管理服务 (M2-18~20)
 *
 * 提供多语言 SDK 的版本信息、示例代码生成、安装指引。
 */
class SdkManagerService
{
    /**
     * 获取所有 SDK 版本信息
     */
    public function getVersions(): array
    {
        return config('sdk-manager.versions', []);
    }

    /**
     * 获取 SDK 功能矩阵
     */
    public function getFeatureMatrix(): array
    {
        return config('sdk-manager.features', []);
    }

    /**
     * 获取指定语言的 SDK 示例代码
     */
    public function getExampleCode(string $language, string $action = 'activate'): string
    {
        $examples = $this->getExamples();
        return $examples[$language][$action] ?? $examples[$language]['activate'] ?? '# 示例代码不可用';
    }

    /**
     * SDK 示例代码
     */
    public function getExamples(): array
    {
        return [
            'php' => [
                'activate' => "<?php\n\nuse Huwutong\\Client;\n\n\$client = new Client('your_api_key');\n\$result = \$client->activate('LICENSE-KEY', [\n    'machine_id' => 'unique-id',\n    'hostname' => gethostname(),\n]);\necho '激活成功: ' . \$result['expires_at'];\n",
                'validate' => "<?php\n\n\$client = new Client('your_api_key');\n\$result = \$client->validate('LICENSE-KEY', ['machine_id' => 'unique-id']);\nif (\$result['valid']) {\n    echo 'License 有效';\n} else {\n    echo 'License 无效: ' . \$result['message'];\n}\n",
            ],
            'python' => [
                'activate' => "from huwutong_sdk import HWTClient\n\nclient = HWTClient(api_key='your_api_key')\nresult = client.activate('LICENSE-KEY', {\n    'machine_id': 'unique-id',\n    'hostname': 'server-01',\n})\nprint(f'激活成功, 到期: {result.expires_at}')\n",
                'validate' => "result = client.validate('LICENSE-KEY', {'machine_id': 'unique-id'})\nif result.is_valid:\n    print('License 有效')\nelse:\n    print(f'License 无效: {result.message}')\n",
            ],
            'go' => [
                'activate' => "package main\n\nimport (\n    \"log\"\n    \"github.com/huwutong/huwutong-sdk-go/huwutong\"\n)\n\nfunc main() {\n    client := huwutong.NewClient(\"your_api_key\", \"https://api.huwutong.com\")\n    result, err := client.Activate(\"LICENSE-KEY\", map[string]interface{}{\n        \"machine_id\": \"unique-id\",\n    })\n    if err != nil {\n        log.Fatal(err)\n    }\n    log.Printf(\"激活成功, 到期: %s\", result.ExpiresAt)\n}\n",
                'validate' => "result, err := client.Validate(\"LICENSE-KEY\", map[string]interface{}{\n    \"machine_id\": \"unique-id\",\n})\nif err != nil {\n    log.Fatal(err)\n}\nif result.IsValid {\n    log.Println(\"License 有效\")\n}\n",
            ],
            'java' => [
                'activate' => "import com.huwutong.sdk.HWTClient;\n\nHWTClient client = new HWTClient(\"your_api_key\");\nActivationResult result = client.activate(\"LICENSE-KEY\", Map.of(\n    \"machine_id\", \"unique-id\"\n));\nSystem.out.println(\"激活成功, 到期: \" + result.getExpiresAt());\n",
                'validate' => "ValidationResult result = client.validate(\"LICENSE-KEY\", Map.of(\n    \"machine_id\", \"unique-id\"\n));\nif (result.isValid()) {\n    System.out.println(\"License 有效\");\n}\n",
            ],
            'node' => [
                'activate' => "const { HWTClient } = require('huwutong-sdk');\n\nconst client = new HWTClient({ apiKey: 'your_api_key' });\nconst result = await client.activate('LICENSE-KEY', {\n    machine_id: 'unique-id',\n});\nconsole.log(`激活成功, 到期: \${result.expiresAt}`);\n",
                'validate' => "const result = await client.validate('LICENSE-KEY', {\n    machine_id: 'unique-id',\n});\nif (result.isValid) {\n    console.log('License 有效');\n}\n",
            ],
        ];
    }
}
