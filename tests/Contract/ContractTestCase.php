<?php

namespace Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * 契约测试基类
 *
 * 所有契约测试（消费者 + 提供者）的公共基类。
 * 提供契约测试专用的断言和辅助方法。
 */
class ContractTestCase extends BaseTestCase
{
    /**
     * 契约通过阈值: 允许的失败百分比
     */
    protected float $failureThreshold = 0.0;

    /**
     * 断言 API 响应符合 Pact 契约
     *
     * @param array $interaction Pact 交互定义
     * @param int   $status 实际 HTTP 状态码
     * @param array $body   实际响应体
     * @param string $message 失败消息
     */
    protected function assertMatchesPact(
        array $interaction,
        int $status,
        array $body,
        string $message = ''
    ): void {
        $errors = [];
        $verified = PactContract::verifyResponse($interaction, $status, $body, $errors);

        $errorMsg = $message ?: 'API 响应不符合 Pact 契约';
        if (!$verified) {
            $errorMsg .= "\n" . implode("\n", $errors);
        }

        $this->assertTrue($verified, $errorMsg);
    }
}
