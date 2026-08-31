<?php

namespace App\Services;

use App\Models\ContentSignature;
use Illuminate\Support\Facades\Log;

/**
 * AI 内容溯源 / 数字签名服务
 *
 * 为 AI 生成内容添加哈希存证 + HMAC 数字签名，
 * 提供内容真实性验证和防篡改能力。
 */
class ContentSignatureService
{
    protected string $hmacKey;

    public function __construct()
    {
        // 从配置或应用密钥派生签名密钥
        $key = config('app.key');
        if (!$key || $key === 'base64:...') {
            throw new \RuntimeException(__("app.content_signature.app_key_not_configured_cannot_init_signing"));
        }
        $this->hmacKey = $key;
    }

    protected function ensureInitialized(): void
    {
        if (empty($this->hmacKey)) {
            throw new \RuntimeException(__("app.content_signature.signing_service_not_initialized"));
        }
    }

    /**
     * 为内容生成数字签名并存证
     *
     * @return array{hash: string, signature: string, key_id: string, signed_at: string}
     */
    public function sign(
        string $content,
        string $sourceType = 'ai_reply',
        ?int $sourceId = null,
        ?array $metadata = null,
    ): array {
        $keyId = $this->getKeyId();
        $preview = mb_substr(strip_tags($content), 0, 200);

        // 计算内容哈希
        $hash = $this->hash($content);

        // 检查是否已签名（去重）
        $existing = ContentSignature::where('content_hash', $hash)->first();
        if ($existing) {
            return [
                'hash' => $existing->content_hash,
                'signature' => $existing->signature,
                'key_id' => $existing->signing_key_id,
                'signed_at' => $existing->signed_at->toIso8601String(),
                'id' => $existing->id,
            ];
        }

        // 生成签名
        $signature = $this->generateSignature($hash, $keyId);

        // 持久化
        $record = ContentSignature::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'content_hash' => $hash,
            'signature' => $signature,
            'signing_key_id' => $keyId,
            'content_preview' => $preview,
            'metadata' => $metadata,
            'signed_at' => now(),
        ]);

        Log::info("[ContentSignature] 已签名 {$sourceType}#{$sourceId}: {$hash}");

        return [
            'id' => $record->id,
            'hash' => $hash,
            'signature' => $signature,
            'key_id' => $keyId,
            'signed_at' => $record->signed_at->toIso8601String(),
        ];
    }

    /**
     * 验证内容的数字签名
     *
     * @return array{verified: bool, hash: string, record: ?array, message: string}
     */
    public function verify(string $content, ?string $expectedHash = null): array
    {
        $hash = $this->hash($content);

        // 如果提供了期望的哈希，先比较
        if ($expectedHash && $hash !== $expectedHash) {
            return [
                'verified' => false,
                'hash' => $hash,
                'record' => null,
                'message' => __('app.content_signature.hash_mismatch'),
            ];
        }

        // 查找签名记录
        $record = ContentSignature::where('content_hash', $hash)->first();

        if (!$record) {
            return [
                'verified' => false,
                'hash' => $hash,
                'record' => null,
                'message' => __('app.content_signature.no_record'),
            ];
        }

        // 验证签名
        $expectedSignature = $this->generateSignature($hash, $record->signing_key_id);

        if ($record->signature !== $expectedSignature) {
            return [
                'verified' => false,
                'hash' => $hash,
                'record' => $record->toArray(),
                'message' => __('app.content_signature.verification_failed'),
            ];
        }

        return [
            'verified' => true,
            'hash' => $hash,
            'record' => $record->toArray(),
            'message' => __('app.content_signature.verified'),
            'signed_at' => $record->signed_at->toIso8601String(),
            'source_type' => $record->source_type,
            'content_preview' => $record->content_preview,
        ];
    }

    /**
     * 在内容末尾追加数字签名标记
     */
    public function appendSignatureMark(string $content, string $sourceType = 'ai_reply', ?int $sourceId = null): array
    {
        $signature = $this->sign($content, $sourceType, $sourceId);

        $hashShort = mb_substr($signature['hash'], 0, 12);
        $signedAt = mb_substr($signature['signed_at'], 0, 10);
        $verifyUrl = $this->getVerifyUrl($signature['hash']);

        $mark = "\n\n---\n🔐 *AI 内容存证* | 哈希: `{$hashShort}...` | 签名时间: {$signedAt} | [验证]({$verifyUrl})";

        return [
            'content' => $content . $mark,
            'signature' => $signature,
        ];
    }

    /**
     * 获取存证统计
     */
    public function getStats(): array
    {
        $total = ContentSignature::count();

        return [
            'total_signed' => $total,
            'by_source' => ContentSignature::selectRaw('source_type, count(*) as total')
                ->groupBy('source_type')
                ->pluck('total', 'source_type')
                ->toArray(),
            'recent' => ContentSignature::latest()->take(5)->get(['id', 'content_preview', 'source_type', 'signed_at']),
        ];
    }

    // ═══════════════════════════════════════════
    //  内部方法
    // ═══════════════════════════════════════════

    protected function hash(string $content): string
    {
        return hash('sha256', $content);
    }

    protected function generateSignature(string $hash, string $keyId): string
    {
        $key = $this->getKey($keyId);
        return hash_hmac('sha256', $hash, $key);
    }

    protected function getKeyId(): string
    {
        return 'v1_' . mb_substr(md5($this->hmacKey), 0, 8);
    }

    protected function getKey(string $keyId): string
    {
        // 支持多版本密钥轮换
        return $this->hmacKey . ':' . $keyId;
    }

    protected function getVerifyUrl(string $hash): string
    {
        return url("/api/content-signatures/verify?hash={$hash}");
    }
}
