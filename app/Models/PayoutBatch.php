<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 打款批次
 *
 * @property int $id
 * @property string $batch_no
 * @property string|null $title
 * @property string $channel bank/alipay/wechat/paypal
 * @property int $total_count
 * @property float $total_amount
 * @property float $total_fee
 * @property string $status pending/processing/completed/partial_failed/failed
 * @property int $created_by
 * @property string|null $notes
 * @property string|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PayoutBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_no', 'title', 'channel', 'total_count', 'total_amount',
        'total_fee', 'status', 'created_by', 'notes', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_fee' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'batch_no', 'batch_no');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    // ── Helpers ──

    public function getSuccessCountAttribute(): int
    {
        return $this->withdrawals()->where('status', 'completed')->count();
    }

    public function getFailCountAttribute(): int
    {
        return $this->withdrawals()->whereIn('status', ['failed', 'rejected'])->count();
    }

    public function getSuccessAmountAttribute(): float
    {
        return (float) $this->withdrawals()->where('status', 'completed')->sum('amount');
    }

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

    public static function generateBatchNo(): string
    {
        $prefix = 'PO' . now()->format('Ymd');
        $last = static::where('batch_no', 'like', "{$prefix}%")
            ->orderBy('batch_no', 'desc')
            ->value('batch_no');

        if ($last) {
            $seq = (int) substr($last, -4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
