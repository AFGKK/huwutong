<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentSecurityLog;
use App\Models\PaymentWebhookLog;
use App\Models\AttackIpBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 支付安全防护 (M2-153 🛒)
 *
 * - 防重复支付校验
 * - 防篡改金额校验
 * - 支付回调签名验证
 * - 退款防刷
 * - 安全审计
 */
class PaymentSecurityGuard
{
    /**
     * 校验防重复支付
     */
    public function checkDuplicatePayment(int $orderId, string $transactionId): array
    {
        $existing = Order::where('id', $orderId)->first();
        if (!$existing) {
            return $this->result(false, '订单不存在', 'duplicate_payment', 'medium');
        }
        if ($existing->status === Order::STATUS_PAID) {
            $this->log($orderId, 'duplicate_payment', false, [
                'existing_status' => $existing->status,
                'new_transaction' => $transactionId,
            ], 'high');
            return $this->result(false, '订单已支付，禁止重复支付', 'duplicate_payment', 'high');
        }

        $this->log($orderId, 'duplicate_payment', true);
        return $this->result(true);
    }

    /**
     * 防篡改金额校验
     */
    public function checkAmountTamper(int $orderId, float $paidAmount, string $currency = 'CNY'): array
    {
        $order = Order::find($orderId);
        if (!$order) {
            return $this->result(false, '订单不存在', 'amount_tamper', 'high');
        }

        $expected = (float) $order->final_amount;
        $diff = abs($paidAmount - $expected);

        // 允许1分钱误差
        if ($diff > 0.01) {
            $this->log($orderId, 'amount_tamper', false, [
                'expected' => $expected,
                'paid' => $paidAmount,
                'diff' => $diff,
                'currency' => $currency,
            ], 'critical');
            return $this->result(false, "金额不匹配: 应付{$expected}, 实付{$paidAmount}", 'amount_tamper', 'critical');
        }

        $this->log($orderId, 'amount_tamper', true);
        return $this->result(true);
    }

    /**
     * 校验支付回调签名
     */
    public function verifyCallbackSignature(string $gateway, array $payload, array $signatureData): array
    {
        // 各网关签名校验由具体Gateway实现，这里做通用检查
        $eventId = $payload['id'] ?? $payload['event_id'] ?? '';

        if (empty($eventId)) {
            return $this->result(false, '回调缺少event_id', 'signature_verify', 'high');
        }

        // 检查event_id是否已被处理
        $exists = PaymentWebhookLog::where('event_id', $eventId)->exists();
        if ($exists) {
            return $this->result(false, '回调重复', 'signature_verify', 'medium');
        }

        $this->log(null, 'signature_verify', true, [
            'gateway' => $gateway,
            'event_id' => $eventId,
        ]);
        return $this->result(true);
    }

    /**
     * 退款防刷检查
     */
    public function checkRefundAbuse(int $customerId, int $orderId): array
    {
        $recentRefunds = \App\Models\Refund::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $totalRefundAmount = \App\Models\Refund::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'completed')
            ->sum('amount');

        $order = Order::find($orderId);
        $orderAmount = $order ? (float) $order->final_amount : 0;

        $flags = [];
        $riskLevel = 'low';

        if ($recentRefunds >= 5) {
            $flags[] = "近30天退款{$recentRefunds}次";
            $riskLevel = 'high';
        }
        if ($recentRefunds >= 3) {
            $flags[] = "近30天退款{$recentRefunds}次";
            $riskLevel = $riskLevel === 'low' ? 'medium' : $riskLevel;
        }
        if ($totalRefundAmount > 5000) {
            $flags[] = "累计退款{$totalRefundAmount}元";
            $riskLevel = $riskLevel === 'low' ? 'medium' : $riskLevel;
        }
        if (empty($order)) {
            $flags[] = '订单不存在';
            $riskLevel = 'high';
        }

        $passed = $riskLevel !== 'high';
        $this->log($orderId, 'refund_abuse', $passed, [
            'customer_id' => $customerId,
            'recent_refunds' => $recentRefunds,
            'total_refund_amount' => $totalRefundAmount,
            'flags' => $flags,
        ], $riskLevel);

        return $this->result($passed, $passed ? null : '退款申请触发风控', 'refund_abuse', $riskLevel);
    }

    /**
     * IP风控检查
     */
    public function checkIpRisk(string $ip, int $orderId = null): array
    {
        // 检查IP是否在黑名单（Cache + 数据库双重校验）
        $isBlacklisted = Cache::get("banned:ip:{$ip}", false);

        if (!$isBlacklisted) {
            // 数据库检查：是否有未过期的封禁记录
            $block = AttackIpBlock::where('ip', $ip)
                ->where(function ($q) {
                    $q->where('expires_at', '>', now())
                      ->orWhere('is_permanent', true);
                })
                ->first();
            $isBlacklisted = $block !== null;
        }

        if ($isBlacklisted) {
            $this->log($orderId, 'ip_check', false, ['ip' => $ip, 'reason' => 'blacklisted'], 'high');
            return $this->result(false, 'IP已被列入黑名单', 'ip_check', 'high');
        }

        $this->log($orderId, 'ip_check', true, ['ip' => $ip]);
        return $this->result(true);
    }

    /**
     * 综合安全校验（下单前执行）
     */
    public function prePaymentCheck(int $orderId, string $ip, float $amount, string $currency): array
    {
        $checks = [
            'amount_tamper' => $this->checkAmountTamper($orderId, $amount, $currency),
            'ip_check' => $this->checkIpRisk($ip, $orderId),
        ];

        $allPassed = true;
        $failures = [];
        foreach ($checks as $name => $result) {
            if (!$result['passed']) {
                $allPassed = false;
                $failures[$name] = $result['message'];
            }
        }

        return [
            'passed' => $allPassed,
            'failures' => $failures,
            'checks' => $checks,
        ];
    }

    /**
     * 获取安全审计日志
     */
    public function getSecurityLogs(array $filters = []): array
    {
        $query = PaymentSecurityLog::with('order:id,order_no');
        if (!empty($filters['risk_level'])) $query->where('risk_level', $filters['risk_level']);
        if (!empty($filters['check_type'])) $query->where('check_type', $filters['check_type']);
        if (!empty($filters['order_id'])) $query->where('order_id', $filters['order_id']);

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->toArray();
    }

    /**
     * 获取安全统计
     */
    public function getStats(): array
    {
        return [
            'total_checks' => PaymentSecurityLog::count(),
            'passed' => PaymentSecurityLog::where('passed', true)->count(),
            'failed' => PaymentSecurityLog::where('passed', false)->count(),
            'by_level' => PaymentSecurityLog::selectRaw('risk_level, COUNT(*) as cnt')
                ->groupBy('risk_level')->pluck('cnt', 'risk_level')->toArray(),
            'by_type' => PaymentSecurityLog::selectRaw('check_type, COUNT(*) as cnt')
                ->groupBy('check_type')->pluck('cnt', 'check_type')->toArray(),
            'critical_today' => PaymentSecurityLog::where('risk_level', 'critical')
                ->whereDate('created_at', today())->count(),
        ];
    }

    protected function log(?int $orderId, string $type, bool $passed, array $details = [], string $level = 'low'): void
    {
        PaymentSecurityLog::create([
            'order_id' => $orderId,
            'check_type' => $type,
            'passed' => $passed,
            'details' => $details,
            'risk_level' => $level,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected function result(bool $passed, ?string $message = null, string $type = '', string $level = 'low'): array
    {
        return [
            'passed' => $passed,
            'message' => $message,
            'check_type' => $type,
            'risk_level' => $level,
        ];
    }
}
