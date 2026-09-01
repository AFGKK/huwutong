<?php

namespace App\Services;

use App\Models\ApiDocEndpoint;
use Illuminate\Support\Facades\Log;

/**
 * AI SDK 配置生成器 (M2-47)
 *
 * 根据客户输入的项目语言/框架/包管理器，
 * 使用 LLM 自动生成开箱即用的集成代码和依赖配置。
 */
class SdkConfigGeneratorAiService
{
    public function __construct(protected LlmService $llm) {}

    /**
     * 支持的编程语言 & 框架
     */
    public const SUPPORTED_LANGUAGES = [
        'php' => ['laravel', 'symfony', 'thinkphp', 'yii', 'native'],
        'javascript' => ['node', 'express', 'nextjs', 'nuxt', 'react', 'vue'],
        'python' => ['django', 'flask', 'fastapi', 'native'],
        'go' => ['gin', 'echo', 'fiber', 'native'],
        'java' => ['spring', 'spring-boot', 'jakarta-ee', 'native'],
        'ruby' => ['rails', 'sinatra', 'native'],
        'csharp' => ['aspnet', 'dotnet', 'native'],
        'rust' => ['actix', 'axum', 'native'],
    ];

    /**
     * 生成 SDK 集成配置
     */
    public function generate(array $params): array
    {
        $language = $params['language'] ?? 'php';
        $framework = $params['framework'] ?? 'laravel';
        $packageManager = $params['package_manager'] ?? $this->detectPackageManager($language);
        $licenseKey = $params['license_key'] ?? 'YOUR_LICENSE_KEY';
        $apiUrl = $params['api_url'] ?? config('app.url') . '/api';

        // 先尝试 LLM 生成
        $llmResult = $this->generateWithLlm($language, $framework, $packageManager, $licenseKey, $apiUrl);
        if ($llmResult) {
            return $llmResult;
        }

        // 兜底：模板生成
        return $this->generateFromTemplate($language, $framework, $packageManager, $licenseKey, $apiUrl);
    }

    /**
     * LLM 生成
     */
    protected function generateWithLlm(string $language, string $framework, string $pm, string $key, string $url): ?array
    {
        $prompt = json_encode([
            'task' => "为{$language}/{$framework}项目生成互物通License SDK集成代码",
            'language' => $language,
            'framework' => $framework,
            'package_manager' => $pm,
            'license_key' => $key,
            'api_url' => $url,
            'requested_output' => [
                'setup_guide' => '分步安装指南',
                'install_command' => '包管理器安装命令',
                'init_code' => '初始化SDK的代码片段（带注释）',
                'activate_code' => 'License激活示例代码',
                'validate_code' => 'License验证示例代码',
                'device_binding_code' => '设备绑定示例代码',
                'error_handling' => '错误处理示例代码',
                'full_example' => '完整的集成示例文件',
                'dependencies' => '依赖列表（包名+版本）',
                'config_options' => '可配置选项说明',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是SDK技术文档专家，擅长为各种语言生成开箱即用的集成代码。返回JSON。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.3,
            ], 'sdk-config-generator');

            $content = $result['content'] ?? '';
            // 尝试解析JSON
            if (str_starts_with(trim($content), '{')) {
                $parsed = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            }
            // 如果不是JSON，作为纯文本返回
            return [
                'language' => $language,
                'framework' => $framework,
                'llm_response' => $content,
                'setup_guide' => '请参考上述LLM生成的内容',
            ];
        } catch (\Throwable $e) {
            Log::warning('SdkConfigGenerator: LLM failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 模板生成（兜底）
     */
    protected function generateFromTemplate(string $language, string $framework, string $pm, string $key, string $url): array
    {
        $codeExamples = [
            'php' => [
                'install_command' => 'composer require huwutong/huwutong-sdk-php',
                'init_code' => "<?php\n\nuse Huwutong\\Client;\n\n\$client = new Client('{$key}', '{$url}');",
                'activate_code' => "\$client->activate('LICENSE-KEY', [\n    'machine_id' => \$fingerprint,\n    'platform' => PHP_OS,\n]);",
                'validate_code' => "\$result = \$client->validate('LICENSE-KEY');\nif (\$result->isValid) {\n    echo 'License 有效';\n}",
            ],
            'javascript' => [
                'install_command' => 'npm install huwutong-sdk',
                'init_code' => "const { Client } = require('huwutong-sdk');\n\nconst client = new Client('{$key}', '{$url}');",
                'activate_code' => "await client.activate('LICENSE-KEY', { machine_id: deviceFingerprint, platform: process.platform });",
                'validate_code' => "const result = await client.validate('LICENSE-KEY');\nif (result.isValid) {\n  console.log('License 有效');\n}",
            ],
            'python' => [
                'install_command' => 'pip install huwutong-sdk',
                'init_code' => "from huwutong_sdk import HWTClient\n\nclient = HWTClient(api_key='{$key}', host='{$url}')",
                'activate_code' => "client.activate('LICENSE-KEY', {'machine_id': fingerprint, 'platform': 'linux'})",
                'validate_code' => "status = client.validate('LICENSE-KEY')\nif status.is_valid:\n    print('License 有效')",
            ],
        ];

        $examples = $codeExamples[$language] ?? $codeExamples['php'];
        $examples['language'] = $language;
        $examples['framework'] = $framework;
        $examples['setup_guide'] = "## {$language}/{$framework} SDK 集成指南\n\n### 安装\n```bash\n{$examples['install_command']}\n```\n\n### 初始化\n```{$language}\n{$examples['init_code']}\n```";

        return $examples;
    }

    /**
     * 检测包管理器
     */
    protected function detectPackageManager(string $language): string
    {
        return match ($language) {
            'php' => 'composer',
            'javascript' => 'npm',
            'python' => 'pip',
            'go' => 'go mod',
            'java' => 'maven',
            'ruby' => 'gem',
            'csharp' => 'nuget',
            'rust' => 'cargo',
            default => 'composer',
        };
    }

    /**
     * 获取支持的语言/框架列表
     */
    public function getSupportedOptions(): array
    {
        $result = [];
        foreach (self::SUPPORTED_LANGUAGES as $lang => $frameworks) {
            $result[] = [
                'language' => $lang,
                'frameworks' => $frameworks,
                'package_managers' => [$this->detectPackageManager($lang)],
            ];
        }
        return $result;
    }
}
