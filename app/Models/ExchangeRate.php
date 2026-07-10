<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperExchangeRate
 */
class ExchangeRate extends Model
{
    const PROVIDER_MANUAL = 'manual';
    const PROVIDER_ECB = 'ecb';
    const PROVIDER_ALIPAY = 'alipay';
    const PROVIDER_STRIPE = 'stripe';

    const CURRENCY_CNY = 'CNY';
    const CURRENCY_USD = 'USD';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_JPY = 'JPY';
    const CURRENCY_GBP = 'GBP';
    const CURRENCY_HKD = 'HKD';
    const CURRENCY_SGD = 'SGD';
    const CURRENCY_KRW = 'KRW';

    const SUPPORTED_CURRENCIES = [
        self::CURRENCY_CNY,
        self::CURRENCY_USD,
        self::CURRENCY_EUR,
        self::CURRENCY_JPY,
        self::CURRENCY_GBP,
        self::CURRENCY_HKD,
        self::CURRENCY_SGD,
        self::CURRENCY_KRW,
    ];

    const CURRENCY_SYMBOLS = [
        'CNY' => '¥',
        'USD' => '$',
        'EUR' => '€',
        'JPY' => '¥',
        'GBP' => '£',
        'HKD' => 'HK$',
        'SGD' => 'S$',
        'KRW' => '₩',
    ];

    const CURRENCY_NAMES = [
        'CNY' => '人民币',
        'USD' => '美元',
        'EUR' => '欧元',
        'JPY' => '日元',
        'GBP' => '英镑',
        'HKD' => '港币',
        'SGD' => '新加坡元',
        'KRW' => '韩元',
    ];

    protected $fillable = [
        'tenant_id', 'from_currency', 'to_currency',
        'rate', 'provider', 'effective_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 获取当前有效汇率
     */
    public function scopeCurrent($query, string $from, string $to)
    {
        return $query->where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('effective_at');
    }

    /**
     * 获取货币符号
     */
    public static function symbol(string $currency): string
    {
        return self::CURRENCY_SYMBOLS[strtoupper($currency)] ?? $currency;
    }

    /**
     * 获取货币名称
     */
    public static function name(string $currency): string
    {
        return self::CURRENCY_NAMES[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * 格式化金额
     */
    public static function format(float $amount, string $currency): string
    {
        $symbol = self::symbol($currency);
        $decimals = in_array(strtoupper($currency), ['JPY', 'KRW']) ? 0 : 2;
        return $symbol . number_format($amount, $decimals);
    }
}
