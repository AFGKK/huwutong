<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CodeSandboxService
{
    protected array $config;
    protected string $workDir;

    public function __construct()
    {
        $this->config = config('code-sandbox', []);
        $this->workDir = $this->config['work_dir'] ?? storage_path('app/sandbox');
        $this->ensureWorkDir();
    }

    /**
     * 执行代码
     * @return array{success: bool, output: string, error: string, execution_time: float, language: string}
     */
    public function execute(string $code, string $language = 'php'): array
    {
        if (!($this->config['enabled'] ?? true)) {
            return $this->error(__('app.code_sandbox.disabled'));
        }

        $langConfig = $this->config['languages'][$language] ?? null;
        if (!$langConfig || !($langConfig['enabled'] ?? false)) {
            return $this->error(__('app.code_sandbox.language_not_supported', ['language' => $language]));
        }

        $codeLength = mb_strlen($code);
        $maxLength = $this->config['max_code_length'] ?? 5000;
        if ($codeLength > $maxLength) {
            return $this->error(__('app.code_sandbox.code_too_long', ['max' => $maxLength]));
        }

        $startTime = microtime(true);

        try {
            if ($language === 'sql') {
                $result = $this->executeSql($code);
            } else {
                $result = $this->executeProcess($code, $language, $langConfig);
            }

            $result['execution_time'] = round((microtime(true) - $startTime) * 1000, 1);
            $result['language'] = $language;
            $result['code_length'] = $codeLength;

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Code sandbox execution error', [
                'language' => $language,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'output' => '',
                'error' => __('app.code_sandbox.execution_error') . ': ' . $e->getMessage(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 1),
                'language' => $language,
            ];
        }
    }

    /**
     * 创建临时文件并执行
     */
    protected function executeProcess(string $code, string $language, array $langConfig): array
    {
        $binary = $langConfig['binary'] ?? '';
        if (empty($binary)) {
            return $this->error(__('app.code_sandbox.binary_not_configured', ['language' => $language]));
        }

        // 检查二进制是否存在
        if (!$this->binaryExists($binary)) {
            return $this->error(__('app.code_sandbox.runtime_not_found', ['language' => $language]));
        }

        // 写入临时文件
        $ext = $langConfig['extension'] ?? 'txt';
        $sessionId = 'sandbox_' . md5($code . microtime(true));
        $tmpFile = $this->workDir . '/' . $sessionId . '.' . $ext;

        try {
            // 对于 PHP，添加禁用函数和超时
            if ($language === 'php') {
                $disabledFunctions = $langConfig['disabled_functions'] ?? [];
                $disableStr = implode(',', $disabledFunctions);
                $memoryLimit = $this->config['memory_limit'] ?? 128;
                $timeout = $this->config['timeout'] ?? 10;

                $wrappedCode = "<?php\n";
                $wrappedCode .= "error_reporting(E_ALL);\n";
                $wrappedCode .= "set_error_handler(function(\$severity, \$msg, \$file, \$line) {\n";
                $wrappedCode .= "    throw new ErrorException(\$msg, 0, \$severity, \$file, \$line);\n";
                $wrappedCode .= "});\n";
                // 移除代码中的 <?php 标签避免冲突
                $cleanCode = preg_replace('/^<\?php\s*/', '', $code);
                $wrappedCode .= $cleanCode;
                File::put($tmpFile, $wrappedCode);
            } else {
                File::put($tmpFile, $code);
            }

            // 构建命令
            $timeout = $this->config['timeout'] ?? 10;
            $maxOutput = $this->config['max_output_size'] ?? 102400;

            if ($language === 'php') {
                // PHP: 使用 -d 设置配置，-f 执行文件
                $cmd = escapeshellcmd($binary) . " -d disable_functions='{$disableStr}' -d open_basedir='" . $this->workDir . "' -d memory_limit={$memoryLimit}M -d max_execution_time={$timeout} -d display_errors=1 -f " . escapeshellarg($tmpFile);
            } else {
                $cmd = escapeshellcmd($binary) . ' ' . escapeshellarg($tmpFile);
            }

            // Windows 上使用 timeout 命令
            if (PHP_OS_FAMILY === 'Windows') {
                $fullCmd = "cmd /c \"{$cmd}\"";
            } else {
                $fullCmd = "timeout {$timeout} {$cmd} 2>&1";
            }

            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $process = proc_open(
                $language === 'php' ? $cmd : $fullCmd,
                $descriptors,
                $pipes,
                $this->workDir,
                ['PATH' => getenv('PATH')]
            );

            if (!is_resource($process)) {
                return $this->error(__('app.code_sandbox.cannot_create_process'));
            }

            // 关闭 stdin
            fclose($pipes[0]);

            // 读取输出
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            // 限制输出大小
            if (mb_strlen($stdout) > $maxOutput) {
                $stdout = mb_substr($stdout, 0, $maxOutput) . "\n\n... (" . __('app.code_sandbox.output_truncated', ['max' => $maxOutput]) . ")";
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            return [
                'success' => $exitCode === 0,
                'output' => $stdout ?: '',
                'error' => $stderr ?: '',
                'exit_code' => $exitCode,
            ];
        } finally {
            // 清理临时文件
            if (File::exists($tmpFile)) {
                File::delete($tmpFile);
            }
        }
    }

    /**
     * 执行 SQL（只读 SQLite 沙箱）
     */
    protected function executeSql(string $code): array
    {
        $timeout = $this->config['timeout'] ?? 10;
        $maxOutput = $this->config['max_output_size'] ?? 102400;

        // 检查是否是只读查询
        $upper = strtoupper(trim($code));
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|REPLACE|LOAD)\b/i', $upper)) {
            return $this->error(__('app.code_sandbox.select_only'));
        }

        // 使用内存 SQLite 数据库
        $dbPath = ':memory:';

        try {
            $pdo = new \PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_TIMEOUT, $timeout);

            // 创建一些示例表供查询
            $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, created_at TEXT)");
            $pdo->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL, stock INTEGER)");
            $pdo->exec("CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER, product_id INTEGER, quantity INTEGER, total REAL, status TEXT)");

            // 插入示例数据
            $pdo->exec("INSERT INTO users VALUES (1, '张三', 'zhang@example.com', 'admin', '2026-01-01')");
            $pdo->exec("INSERT INTO users VALUES (2, '李四', 'li@example.com', 'user', '2026-02-15')");
            $pdo->exec("INSERT INTO users VALUES (3, '王五', 'wang@example.com', 'user', '2026-03-20')");
            $pdo->exec("INSERT INTO products VALUES (1, '笔记本电脑', 5999.00, 50)");
            $pdo->exec("INSERT INTO products VALUES (2, '机械键盘', 399.00, 200)");
            $pdo->exec("INSERT INTO products VALUES (3, '显示器 4K', 2999.00, 30)");
            $pdo->exec("INSERT INTO orders VALUES (1, 1, 1, 1, 5999.00, 'completed')");
            $pdo->exec("INSERT INTO orders VALUES (2, 2, 2, 2, 798.00, 'pending')");
            $pdo->exec("INSERT INTO orders VALUES (3, 1, 3, 1, 2999.00, 'shipped')");
            $pdo->exec("INSERT INTO orders VALUES (4, 3, 1, 1, 5999.00, 'completed')");

            $stmt = $pdo->query($code);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $output = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            if (mb_strlen($output) > $maxOutput) {
                $output = mb_substr($output, 0, $maxOutput) . "\n\n... (" . __('app.code_sandbox.output_truncated_short') . ")";
            }

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'rows' => count($rows),
            ];
        } catch (\PDOException $e) {
            return $this->error(__('app.code_sandbox.sql_error') . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取支持的语言列表及版本
     */
    public function getSupportedLanguages(): array
    {
        $languages = [];
        foreach (($this->config['languages'] ?? []) as $name => $cfg) {
            $version = null;
            if (!empty($cfg['version_cmd'])) {
                try {
                    $output = [];
                    exec($cfg['version_cmd'] . ' 2>&1', $output, $code);
                    $version = $code === 0 ? ($output[0] ?? '未知') : null;
                } catch (\Throwable $e) {
                    $version = null;
                }
            }
            $languages[$name] = [
                'enabled' => ($cfg['enabled'] ?? false) && ($this->config['enabled'] ?? true),
                'version' => $version,
                'available' => $version !== null,
            ];
        }
        return $languages;
    }

    /**
     * 获取预设代码模板
     */
    public function getTemplates(): array
    {
        return [
            'php' => [
                'label' => __('app.code_sandbox.template_php'),
                'code' => "// PHP 代码沙箱\n\$data = ['name' => '互物通', 'version' => '2.0', 'features' => ['IM', 'AI', 'License']];\necho json_encode(\$data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);\n\n// 数学运算\n\$a = 42;\n\$b = 7;\necho \"\\n计算: {\$a} * {\$b} = \" . (\$a * \$b) . \"\\n\";\n\n// 数组操作\n\$fruits = ['苹果', '香蕉', '橘子'];\nforeach (\$fruits as \$i => \$f) {\n    echo (\$i + 1) . \". {\$f}\\n\";\n}",
            ],
            'python' => [
                'label' => __('app.code_sandbox.template_python'),
                'code' => "# Python 代码沙箱\nimport json\nfrom datetime import datetime\n\ndata = {\n    'name': '互物通',\n    'version': '2.0',\n    'time': datetime.now().strftime('%Y-%m-%d %H:%M:%S')\n}\nprint(json.dumps(data, ensure_ascii=False, indent=2))\n\n# 数学计算\nprint(f\"\\n计算: 42 * 7 = {42 * 7}\")\n\n# 列表处理\nfruits = ['苹果', '香蕉', '橘子']\nfor i, f in enumerate(fruits, 1):\n    print(f\"{i}. {f}\")",
            ],
            'node' => [
                'label' => __('app.code_sandbox.template_node'),
                'code' => "// Node.js 代码沙箱\nconst data = {\n    name: '互物通',\n    version: '2.0',\n    time: new Date().toISOString()\n};\nconsole.log(JSON.stringify(data, null, 2));\n\n// 数学计算\nconsole.log(`\\n计算: 42 * 7 = \${42 * 7}`);\n\n// 数组操作\nconst fruits = ['苹果', '香蕉', '橘子'];\nfruits.forEach((f, i) => console.log(`\${i + 1}. \${f}`));\n\n// 使用内置模块\nconst path = require('path');\nconsole.log(`\\n路径分隔符: \${path.sep}`);",
            ],
            'sql' => [
                'label' => __('app.code_sandbox.template_sql'),
                'code' => "-- SQL 沙箱（只读，使用内存 SQLite）\n-- 内置表: users, products, orders\n\n-- 查询所有用户\nSELECT * FROM users;\n\n-- 多表联查\nSELECT o.id, u.name AS 用户, p.name AS 产品, o.total, o.status\nFROM orders o\nJOIN users u ON o.user_id = u.id\nJOIN products p ON o.product_id = p.id\nORDER BY o.total DESC;",
            ],
        ];
    }

    protected function ensureWorkDir(): void
    {
        if (!File::exists($this->workDir)) {
            File::makeDirectory($this->workDir, 0755, true);
        }
    }

    protected function binaryExists(string $binary): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $check = "where {$binary} 2>nul";
        } else {
            $check = "which {$binary} 2>/dev/null";
        }
        exec($check, $output, $code);
        return $code === 0;
    }

    protected function error(string $message): array
    {
        return ['success' => false, 'output' => '', 'error' => $message, 'exit_code' => -1];
    }
}
