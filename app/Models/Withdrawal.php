<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 提现记录（通用收益）
 *
 * @property int $id
 * @property int $earnings_account_id
 * @property int|null $user_id
 * @property float $amount
 * @property float $fee
 * @property float $net_amount
 * @property string $channel bank/alipay/wechat/paypal
 * @property string|null $channel_account
 * @property string|null $bank_name
 * @property string|null $bank_branch
 * @property string|null $bank_account_name
 * @property string|null $bank_account_no
 * @property string|null $alipay_account
 * @property string|null $wechat_account
 * @property string|null $paypal_email
 * @property string $status pending_review/pending/processing/completed/failed/rejected/cancelled
 * @property string|null $batch_no
 * @property string|null $proof
 * @property string|null $failure_reason
 * @property string|null $transaction_id
 * @property string|null $remark
 * @property int|null $reviewed_by
 * @property string|null $reviewed_at
 * @property string|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin IdeHelperWithdrawal
 */
class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'earnings_account_id', 'user_id', 'amount', 'fee', 'net_amount',
        'channel', 'channel_account',
        'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_no',
        'alipay_account', 'wechat_account', 'paypal_email',
        'status', 'batch_no', 'proof', 'failure_reason',
        'transaction_id', 'remark', 'reviewed_by', 'reviewed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function earningsAccount(): BelongsTo
    {
        return $this->belongsTo(EarningsAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payoutBatch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'batch_no', 'batch_no');
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending_review', 'pending']);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByBatch($query, string $batchNo)
    {
        return $query->where('batch_no', $batchNo);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ──

    public function getChannelDisplayAttribute(): string
    {
        return match ($this->channel) {
            'bank' => '银行卡',
            'alipay' => '支付宝',
            'wechat' => '微信',
            'paypal' => 'PayPal',
            default => $this->channel,
        };
    }

    public function getChannelAccountMaskedAttribute(): string
    {
        if ($this->channel === 'bank' && $this->bank_account_no) {
            $no = $this->bank_account_no;
            return '****' . substr($no, -4);
        }
        if ($this->channel === 'alipay' && $this->alipay_account) {
            $acc = $this->alipay_account;
            return substr($acc, 0, 3) . '****' . substr($acc, -3);
        }
        if ($this->channel === 'wechat' && $this->wechat_account) {
            $acc = $this->wechat_account;
            return substr($acc, 0, 2) . '****' . substr($acc, -2);
        }
        if ($this->channel === 'paypal' && $this->paypal_email) {
            $email = $this->paypal_email;
            $parts = explode('@', $email);
            return substr($parts[0], 0, 2) . '***@' . $parts[1] ?? '';
        }
        return $this->channel_account ?? '-';
    }
}
