<?php

namespace App\Services;

/**
 * 离线验证结果值对象
 */
class OfflineVerificationResult
{
    /**
     * 验证是否通过
     */
    public readonly bool $isValid;

    /**
     * @param bool $isValid 验证是否通过
     * @param string $message 结果描述
     * @param array|null $payload 原始 License 载荷
     * @param array|null $meta 额外元信息（公钥、算法等）
     * @param string|null $errorCode 错误码
     */
    protected function __construct(
        bool          $isValid,
        public readonly string  $message,
        public readonly ?array  $payload = null,
        public readonly ?array  $meta = null,
        public readonly ?string $errorCode = null,
    ) {
        $this->isValid = $isValid;
    }

    /**
     * 创建有效结果
     */
    public static function valid(string $message, array $payload, array $meta = []): self
    {
        return new self(true, $message, $payload, $meta);
    }

    /**
     * 创建无效结果
     */
    public static function invalid(string $errorCode, string $message, array $meta = []): self
    {
        return new self(false, $message, null, $meta, $errorCode);
    }

    /**
     * 创建过期结果
     */
    public static function expired(string $message, array $meta = []): self
    {
        return new self(false, $message, null, $meta, 'LICENSE_EXPIRED');
    }

    /**
     * 创建吊销结果
     */
    public static function revoked(string $message): self
    {
        return new self(false, $message, null, null, 'LICENSE_REVOKED');
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid,
            'message' => $this->message,
            'payload' => $this->payload,
            'meta' => $this->meta,
            'error_code' => $this->errorCode,
        ];
    }
}
