<?php

namespace App\Contracts;

use App\Models\LlmProvider;

interface LlmProviderContract
{
    /**
     * 初始化 Provider
     */
    public function initialize(LlmProvider $config): void;

    /**
     * 获取 Provider 标识
     */
    public function driver(): string;

    /**
     * 文本对话（非流式）
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * 流式对话（返回 Generator）
     */
    public function chatStream(array $messages, array $options = []): \Generator;

    /**
     * 获取可用模型列表
     */
    public function listModels(): array;

    /**
     * 测试连接
     */
    public function testConnection(): array;

    /**
     * 获取 Token 计费单价（每 1K tokens, USD）
     */
    public function getPricing(string $model): array;
}
