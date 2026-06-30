<?php

namespace App\Services;

use App\Models\ElectronicSignature;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PRAC-012 电子签名消息服务
 *
 * 为合同/审批等场景提供消息级电子签名能力：
 * - 单方签署（single）
 * - 多方顺序签署（multi）
 * - 审批流签署（approval）
 */
class ElectronicSignatureService
{
    /**
     * 发起签署请求
     *
     * @param string $signableType 签名对象类型
     * @param int $signableId 签名对象ID
     * @param array $signerIds 签署人ID列表（按顺序）
     * @param string $type single/multi/approval
     * @param int $expiresInDays 过期天数
     * @return array{signatures: array, share_link: ?string}
     */
    public function create(
        string $signableType,
        int $signableId,
        array $signerIds,
        string $type = 'multi',
        ?int $expiresInDays = 30,
    ): array {
        $content = $this->getContentForSigning($signableType, $signableId);
        $contentHash = hash('sha256', $content);

        $signatures = [];
        foreach ($signerIds as $i => $userId) {
            $sig = ElectronicSignature::create([
                'signable_type' => $signableType,
                'signable_id' => $signableId,
                'user_id' => $userId,
                'signature_hash' => $contentHash,
                'status' => 'pending',
                'type' => $type,
                'sequence' => $i + 1,
                'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            ]);
            $signatures[] = $sig;
        }

        Log::info("[ElectronicSignature] 发起签署 {$signableType}#{$signableId}", [
            'signers' => $signerIds, 'type' => $type,
        ]);

        return [
            'signatures' => $signatures,
            'content_hash' => $contentHash,
        ];
    }

    /**
     * 执行签署
     */
    public function sign(int $signatureId, int $userId, string $ip = null): array
    {
        $sig = ElectronicSignature::findOrFail($signatureId);

        if ($sig->user_id !== $userId) {
            return ['success' => false, 'message' => '非指定签署人'];
        }

        if ($sig->status !== 'pending') {
            return ['success' => false, 'message' => '已签署或已过期'];
        }

        if ($sig->expires_at && $sig->expires_at->isPast()) {
            $sig->update(['status' => 'expired']);
            return ['success' => false, 'message' => '签署已过期'];
        }

        // 顺序检查：multi/approval 类型需要按序签署
        if (in_array($sig->type, ['multi', 'approval']) && $sig->sequence > 1) {
            $prevSigned = ElectronicSignature::where('signable_type', $sig->signable_type)
                ->where('signable_id', $sig->signable_id)
                ->where('sequence', $sig->sequence - 1)
                ->where('status', 'signed')
                ->exists();
            if (!$prevSigned) {
                return ['success' => false, 'message' => '需等待前一位签署人完成签署'];
            }
        }

        // 生成签名数据
        $signData = $this->generateSignData($sig, $ip);

        $sig->update([
            'status' => 'signed',
            'signature_data' => json_encode($signData),
            'ip_address' => $ip,
            'signed_at' => now(),
        ]);

        Log::info("[ElectronicSignature] 签署完成 #{$signatureId}", [
            'user' => $userId, 'type' => $sig->type,
        ]);

        // 检查是否全部签署完成
        $allSigned = !ElectronicSignature::where('signable_type', $sig->signable_type)
            ->where('signable_id', $sig->signable_id)
            ->where('status', 'pending')
            ->exists();

        return [
            'success' => true,
            'message' => $allSigned ? '签署完成（全部签署）' : '签署成功',
            'signature' => $sig->fresh(),
            'all_signed' => $allSigned,
        ];
    }

    /**
     * 拒绝签署
     */
    public function reject(int $signatureId, int $userId, string $remark = ''): array
    {
        $sig = ElectronicSignature::findOrFail($signatureId);

        if ($sig->user_id !== $userId) {
            return ['success' => false, 'message' => '非指定签署人'];
        }

        $sig->update([
            'status' => 'rejected',
            'remark' => $remark,
            'signed_at' => now(),
        ]);

        Log::info("[ElectronicSignature] 拒绝签署 #{$signatureId}", [
            'user' => $userId, 'remark' => $remark,
        ]);

        return ['success' => true, 'message' => '已拒绝签署'];
    }

    /**
     * 验证签名链
     */
    public function verify(string $signableType, int $signableId): array
    {
        $signatures = ElectronicSignature::where('signable_type', $signableType)
            ->where('signable_id', $signableId)
            ->orderBy('sequence')
            ->get();

        if ($signatures->isEmpty()) {
            return ['verified' => false, 'message' => '未找到签名记录'];
        }

        $content = $this->getContentForSigning($signableType, $signableId);
        $expectedHash = hash('sha256', $content);

        $results = [];
        $allSigned = true;
        $chainValid = true;

        foreach ($signatures as $sig) {
            $result = [
                'user' => $sig->user?->name ?? "用户#{$sig->user_id}",
                'status' => $sig->status,
                'sequence' => $sig->sequence,
                'signed_at' => $sig->signed_at?->toIso8601String(),
            ];

            if ($sig->status !== 'signed') {
                $allSigned = false;
            }

            // 验证内容哈希一致性
            if ($sig->signature_hash !== $expectedHash) {
                $chainValid = false;
                $result['hash_match'] = false;
            } else {
                $result['hash_match'] = true;
            }

            $results[] = $result;
        }

        return [
            'verified' => $allSigned && $chainValid,
            'all_signed' => $allSigned,
            'chain_valid' => $chainValid,
            'content_hash' => $expectedHash,
            'signatures' => $results,
            'total_signers' => $signatures->count(),
            'signed_count' => $signatures->where('status', 'signed')->count(),
        ];
    }

    /**
     * 获取签署状态统计
     */
    public function getStats(): array
    {
        return [
            'total' => ElectronicSignature::count(),
            'by_status' => ElectronicSignature::selectRaw('status, count(*) as total')
                ->groupBy('status')->pluck('total', 'status')->toArray(),
            'by_type' => ElectronicSignature::selectRaw('type, count(*) as total')
                ->groupBy('type')->pluck('total', 'type')->toArray(),
        ];
    }

    // ═══════════════════════════════════════════
    //  内部方法
    // ═══════════════════════════════════════════

    protected function getContentForSigning(string $type, int $id): string
    {
        // 目前支持会话消息签名
        if ($type === 'conversation_message') {
            $msg = ConversationMessage::find($id);
            return $msg ? ($msg->content ?? '') : '';
        }
        return '';
    }

    protected function generateSignData(ElectronicSignature $sig, ?string $ip): array
    {
        $timestamp = now()->toIso8601String();
        $payload = "{$sig->id}:{$sig->signature_hash}:{$sig->user_id}:{$timestamp}:{$ip}";
        $signingKey = config('app.key');
        if (!$signingKey || $signingKey === 'base64:...') {
            throw new \RuntimeException('APP_KEY 未配置，无法生成签名');
        }
        $hmac = hash_hmac('sha256', $payload, $signingKey);

        return [
            'algorithm' => 'HMAC-SHA256',
            'timestamp' => $timestamp,
            'fingerprint' => $hmac,
            'ip' => $ip,
        ];
    }
}
