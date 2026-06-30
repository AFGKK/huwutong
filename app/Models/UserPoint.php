<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPoint extends Model
{
    protected $fillable = ['user_id', 'balance', 'total_earned', 'total_spent'];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_spent' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 为用户增加积分（需在事务中调用）
     */
    public static function earn(int $userId, float $amount, string $description, ?Model $reference = null): UserPoint
    {
        $points = self::firstOrCreate(['user_id' => $userId]);
        $before = $points->balance;
        $points->increment('balance', $amount);
        $points->increment('total_earned', $amount);
        $points->refresh();

        PointTransaction::create([
            'user_id' => $userId,
            'type' => 'earn',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $points->balance,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
        ]);

        return $points;
    }

    /**
     * 从用户扣除积分（需在事务中调用）
     */
    public static function spend(int $userId, float $amount, string $description, ?Model $reference = null): ?UserPoint
    {
        $points = self::where('user_id', $userId)->first();
        if (! $points || $points->balance < $amount) {
            return null; // 余额不足
        }

        $before = $points->balance;
        $points->decrement('balance', $amount);
        $points->increment('total_spent', $amount);
        $points->refresh();

        PointTransaction::create([
            'user_id' => $userId,
            'type' => 'spend',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $points->balance,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
        ]);

        return $points;
    }
}
