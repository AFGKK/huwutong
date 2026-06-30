<?php

namespace Tests\Contract;

/**
 * Pact 契约定义基类
 *
 * 定义消费者驱动的契约格式，生成 Pact 规范兼容的 JSON 文件。
 * 消费者（SDK）定义期望的 API 交互→写入 pact 文件→提供者（Laravel）验证。
 *
 * @see https://docs.pact.io/
 */
class PactContract
{
    /**
     * 生成 Pact 契约 JSON 文件
     *
     * @param string $consumer 消费者名称（如 "PHP SDK"）
     * @param string $provider 提供者名称（如 "HWT License API"）
     * @param array  $interactions 交互定义数组
     * @param string $version 契约版本
     * @return string 生成的 JSON 内容
     */
    public static function generate(
        string $consumer,
        string $provider,
        array $interactions,
        string $version = '1.0.0'
    ): string {
        $pact = [
            'consumer' => ['name' => $consumer],
            'provider' => ['name' => $provider],
            'metadata' => [
                'pactSpecification' => ['version' => '3.0.0'],
                'pact-php' => ['version' => $version],
            ],
            'interactions' => [],
        ];

        foreach ($interactions as $i => $interaction) {
            $pact['interactions'][] = [
                'description' => $interaction['description'] ?? "Interaction #{$i}",
                'providerState' => $interaction['providerState'] ?? null,
                'request' => self::buildRequest($interaction['request'] ?? []),
                'response' => self::buildResponse($interaction['response'] ?? []),
            ];
        }

        return json_encode($pact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 保存 Pact 契约到文件
     */
    public static function saveToFile(string $consumer, string $provider, string $content): string
    {
        $dir = dirname(__DIR__, 2) . '/pacts';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = str_replace([' ', '/'], '_', "{$consumer}-{$provider}.json");
        $path = "{$dir}/{$filename}";
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * 从文件加载 Pact 契约
     */
    public static function loadFromFile(string $consumer, string $provider): ?array
    {
        $filename = str_replace([' ', '/'], '_', "{$consumer}-{$provider}.json");
        $path = dirname(__DIR__, 2) . "/pacts/{$filename}";

        if (!file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * 构建 Pact 兼容的请求结构
     */
    private static function buildRequest(array $request): array
    {
        return [
            'method' => strtoupper($request['method'] ?? 'GET'),
            'path' => $request['path'] ?? '/',
            'query' => $request['query'] ?? null,
            'headers' => array_merge(
                ['Accept' => 'application/json'],
                $request['headers'] ?? []
            ),
            'body' => $request['body'] ?? null,
            'matchingRules' => $request['matchingRules'] ?? [],
        ];
    }

    /**
     * 构建 Pact 兼容的响应结构
     */
    private static function buildResponse(array $response): array
    {
        return [
            'status' => $response['status'] ?? 200,
            'headers' => array_merge(
                ['Content-Type' => 'application/json'],
                $response['headers'] ?? []
            ),
            'body' => $response['body'] ?? null,
            'matchingRules' => $response['matchingRules'] ?? [],
        ];
    }

    /**
     * 验证提供者响应是否符合契约
     *
     * @param array $interaction Pact 交互定义
     * @param int   $actualStatus 实际 HTTP 状态码
     * @param array $actualBody   实际响应体（已解码为数组）
     * @param array $errors       错误收集数组（引用）
     * @return bool 是否通过验证
     */
    public static function verifyResponse(
        array $interaction,
        int $actualStatus,
        array $actualBody,
        array &$errors = []
    ): bool {
        $passed = true;
        $expected = $interaction['response'];

        // 验证状态码
        $expectedStatus = $expected['status'] ?? 200;
        if ($actualStatus !== $expectedStatus) {
            $errors[] = sprintf(
                '[%s] 状态码不符: 期望 %d, 实际 %d',
                $interaction['description'] ?? 'unknown',
                $expectedStatus,
                $actualStatus
            );
            $passed = false;
        }

        // 验证响应体结构（递归匹配）
        $expectedBody = $expected['body'] ?? [];
        if (!empty($expectedBody)) {
            $bodyErrors = [];
            self::matchStructure($expectedBody, $actualBody, '', $bodyErrors);
            foreach ($bodyErrors as $err) {
                $errors[] = "[{$interaction['description']}] {$err}";
            }
            if (!empty($bodyErrors)) {
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * 递归匹配 JSON 结构
     */
    private static function matchStructure(array $expected, array $actual, string $path, array &$errors): void
    {
        foreach ($expected as $key => $expectedValue) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            if (!array_key_exists($key, $actual)) {
                // 检查是否是可选字段（以 ? 结尾）
                if (!str_ends_with((string)$key, '?')) {
                    $errors[] = "缺少字段: {$currentPath}";
                }
                continue;
            }

            $actualValue = $actual[$key];
            $cleanKey = rtrim((string)$key, '?');

            if (is_array($expectedValue) && is_array($actualValue)) {
                // 检查是否是 Pact 匹配器
                if (isset($expectedValue['pact:matcher:type'])) {
                    self::matchMatcher($expectedValue, $actualValue, $currentPath, $errors);
                } elseif (self::isAssoc($expectedValue)) {
                    // 关联数组 → 递归匹配
                    self::matchStructure($expectedValue, $actualValue, $currentPath, $errors);
                } else {
                    // 索引数组 → 匹配第一个元素的结构
                    if (!empty($expectedValue) && !empty($actualValue)) {
                        self::matchStructure($expectedValue[0], $actualValue[0], "{$currentPath}[0]", $errors);
                    }
                }
            } elseif (is_string($expectedValue) && str_starts_with($expectedValue, '$')) {
                // Pact 匹配器模式：$regex, $any, $email 等
                // 跳过实际值验证，仅验证存在
            } else {
                // 精确值匹配
                if ($expectedValue !== $actualValue) {
                    $errors[] = "字段 {$currentPath} 值不匹配: 期望 '{$expectedValue}', 实际 '{$actualValue}'";
                }
            }
        }
    }

    /**
     * 匹配 Pact 匹配器
     */
    private static function matchMatcher(array $matcher, $actualValue, string $path, array &$errors): void
    {
        $type = $matcher['pact:matcher:type'] ?? '';

        switch ($type) {
            case 'type':
                // 仅验证类型
                $expectedType = $matcher['value'] ?? null;
                if ($expectedType !== null && gettype($actualValue) !== gettype($expectedType)) {
                    $errors[] = "字段 {$path} 类型不匹配: 期望 " . gettype($expectedType) . ", 实际 " . gettype($actualValue);
                }
                break;

            case 'regex':
                // 正则匹配
                $regex = $matcher['regex'] ?? '';
                if ($regex && !preg_match("/{$regex}/", (string)$actualValue)) {
                    $errors[] = "字段 {$path} 不匹配正则: {$regex}, 实际值: {$actualValue}";
                }
                break;

            case 'timestamp':
                // ISO 8601 时间戳验证
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string)$actualValue)) {
                    $errors[] = "字段 {$path} 不是有效时间戳: {$actualValue}";
                }
                break;

            case 'uuid':
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$actualValue)) {
                    $errors[] = "字段 {$path} 不是有效 UUID: {$actualValue}";
                }
                break;

            case 'email':
                if (!filter_var((string)$actualValue, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "字段 {$path} 不是有效邮箱: {$actualValue}";
                }
                break;

            case 'ipAddress':
                if (!filter_var((string)$actualValue, FILTER_VALIDATE_IP)) {
                    $errors[] = "字段 {$path} 不是有效 IP: {$actualValue}";
                }
                break;

            case 'arrayContaining':
                if (!is_array($actualValue)) {
                    $errors[] = "字段 {$path} 应是数组";
                }
                break;

            case 'eachKeyMatches':
                if (is_array($actualValue)) {
                    foreach ($actualValue as $k => $v) {
                        if (!preg_match("/{$matcher['regex']}/", (string)$k)) {
                            $errors[] = "字段 {$path} 的键 '{$k}' 不匹配正则: {$matcher['regex']}";
                        }
                    }
                }
                break;
        }
    }

    private static function isAssoc(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * 获取所有已存储的 Pact 契约摘要
     *
     * @return array<array{consumer: string, provider: string, interactions: int, path: string}>
     */
    public static function listContracts(): array
    {
        $dir = dirname(__DIR__, 2) . '/pacts';
        if (!is_dir($dir)) {
            return [];
        }

        $contracts = [];
        foreach (glob("{$dir}/*.json") as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content) {
                $contracts[] = [
                    'consumer' => $content['consumer']['name'] ?? 'unknown',
                    'provider' => $content['provider']['name'] ?? 'unknown',
                    'interactions' => count($content['interactions'] ?? []),
                    'path' => $file,
                ];
            }
        }

        return $contracts;
    }
}
