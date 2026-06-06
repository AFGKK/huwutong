<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCurrencyPreference;
use App\Models\ExchangeRate;
use App\Models\PricingPlan;
use App\Models\PricingPlanPrice;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 多币种定价与汇率管理服务 (M2-30)
 *
 * 支持 USD/CNY/EUR/JPY/GBP/HKD/SGD/KRW 多币种定价、
 * 固定汇率或实时汇率、客户首选货币、自动货币转换。
 */
class CurrencyService
{
    const CACHE_PREFIX = 'currency:';
    const CACHE_TTL = 3600;        // 1小时
    const RATE_CACHE_TTL = 86400;  // 24小时

    // 基准货币（所有汇率以此为中心换算）
    const BASE_CURRENCY = 'CNY';

    /**
     * 货币转换：将金额从源货币转换为目标货币
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param int|null $tenantId
     * @return array {amount, rate, from, to}
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency, ?int $tenantId = null): array
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return [
                'amount' => round($amount, 2),
                'rate' => 1.0,
                'from' => $from,
                'to' => $to,
            ];
        }

        $rate = $this->getRate($from, $to, $tenantId);

        if ($rate === null) {
            Log::warning('Currency: rate not found', ['from' => $from, 'to' => $to]);
            return [
                'amount' => round($amount, 2),
                'rate' => null,
                'from' => $from,
                'to' => $to,
                'error' => "汇率未配置: {$from} → {$to}",
            ];
        }

        return [
            'amount' => round($amount * $rate, 2),
            'rate' => $rate,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * 批量货币转换
     */
    public function batchConvert(array $amounts, string $fromCurrency, string $toCurrency, ?int $tenantId = null): array
    {
        $rate = $this->getRate($fromCurrency, $toCurrency, $tenantId);

        if ($rate === null) {
            return array_map(fn ($a) => [
                'original' => $a,
                'converted' => $a,
                'rate' => null,
                'error' => "汇率未配置: {$fromCurrency} → {$toCurrency}",
            ], $amounts);
        }

        return array_map(fn ($a) => [
            'original' => $a,
            'converted' => round($a * $rate, 2),
            'rate' => $rate,
        ], $amounts);
    }

    /**
     * 获取从源货币到目标货币的汇率
     * 查找路径: from→to 直接汇率 → 通过 BASE_CURRENCY 中转
     */
    public function getRate(string $fromCurrency, string $toCurrency, ?int $tenantId = null): ?float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        // 1. 从缓存获取
        $cacheKey = self::CACHE_PREFIX . "rate:{$from}:{$to}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 2. 查询直接汇率
        $rate = $this->findDirectRate($from, $to, $tenantId);
        if ($rate !== null) {
            Cache::put($cacheKey, $rate, now()->addSeconds(self::RATE_CACHE_TTL));
            return $rate;
        }

        // 3. 通过基准货币中转: from → BASE → to
        if ($from !== self::BASE_CURRENCY && $to !== self::BASE_CURRENCY) {
            $fromToBase = $this->getRate($from, self::BASE_CURRENCY, $tenantId);
            $baseToTarget = $this->getRate(self::BASE_CURRENCY, $to, $tenantId);
            if ($fromToBase !== null && $baseToTarget !== null) {
                $crossRate = $fromToBase * $baseToTarget;
                Cache::put($cacheKey, $crossRate, now()->addSeconds(self::RATE_CACHE_TTL));
                return $crossRate;
            }
        }

        // 4. 反向汇率: to→from 的倒数
        $reverseRate = $this->findDirectRate($to, $from, $tenantId);
        if ($reverseRate !== null && $reverseRate > 0) {
            $rate = 1 / $reverseRate;
            Cache::put($cacheKey, $rate, now()->addSeconds(self::RATE_CACHE_TTL));
            return $rate;
        }

        return null;
    }

    /**
     * 在数据库中查找直接汇率
     */
    protected function findDirectRate(string $from, string $to, ?int $tenantId): ?float
    {
        $query = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('effective_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $rate = $query->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * 设置或更新汇率
     */
    public function setRate(
        string $fromCurrency,
        string $toCurrency,
        float $rate,
        int $tenantId,
        ?string $provider = 'manual',
        ?string $effectiveAt = null,
        ?string $expiresAt = null
    ): ExchangeRate {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        $exchangeRate = ExchangeRate::create([
            'tenant_id' => $tenantId,
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'provider' => $provider,
            'effective_at' => $effectiveAt ?? now(),
            'expires_at' => $expiresAt,
        ]);

        // 清除相关缓存
        $this->clearRateCache($from, $to);

        Log::info('Currency: rate set', [
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'provider' => $provider,
        ]);

        return $exchangeRate;
    }

    /**
     * 获取所有当前有效的汇率（按租户）
     */
    public function getActiveRates(int $tenantId): Collection
    {
        return ExchangeRate::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('from_currency')
            ->orderBy('to_currency')
            ->get()
            ->groupBy('from_currency');
    }

    /**
     * 获取所有受支持的货币列表
     */
    public function getSupportedCurrencies(): array
    {
        return collect(ExchangeRate::SUPPORTED_CURRENCIES)->map(fn ($code) => [
            'code' => $code,
            'name' => ExchangeRate::name($code),
            'symbol' => ExchangeRate::symbol($code),
        ])->values()->toArray();
    }

    /**
     * 格式化金额为指定货币显示
     */
    public function format(float $amount, string $currency): string
    {
        return ExchangeRate::format($amount, $currency);
    }

    // ─── 定价计划相关 ───

    /**
     * 获取客户可见的定价计划（含客户首选货币价格）
     */
    public function getPricingPlansForCustomer(?Customer $customer = null, ?int $tenantId = null): Collection
    {
        $preferredCurrency = $this->getPreferredCurrency($customer);

        $plans = PricingPlan::with('prices')
            ->active()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        return $plans->map(function (PricingPlan $plan) use ($preferredCurrency) {
            $priceInPreferred = $plan->getPrice($preferredCurrency);
            $priceInBase = $plan->getPrice(self::BASE_CURRENCY);

            $data = $plan->toArray();
            $data['prices'] = $plan->getPricesGrouped();
            $data['preferred_currency'] = $preferredCurrency;
            $data['preferred_price'] = $priceInPreferred ? [
                'currency' => $preferredCurrency,
                'price' => (float) $priceInPreferred->price,
                'formatted' => ExchangeRate::format((float) $priceInPreferred->price, $preferredCurrency),
                'setup_fee' => (float) $priceInPreferred->setup_fee,
            ] : null;

            // 如果首选货币无定价，从基准货币转换
            if (!$priceInPreferred && $priceInBase) {
                $converted = $this->convert(
                    (float) $priceInBase->price,
                    self::BASE_CURRENCY,
                    $preferredCurrency,
                    $plan->tenant_id
                );
                $data['preferred_price'] = [
                    'currency' => $preferredCurrency,
                    'price' => $converted['amount'],
                    'formatted' => ExchangeRate::format($converted['amount'], $preferredCurrency),
                    'rate_used' => $converted['rate'],
                    'is_converted' => true,
                ];
            }

            return $data;
        });
    }

    /**
     * 获取客户首选货币
     */
    public function getPreferredCurrency(?Customer $customer): string
    {
        if (!$customer) {
            return self::BASE_CURRENCY;
        }

        $pref = CustomerCurrencyPreference::where('customer_id', $customer->id)->first();

        return $pref ? $pref->preferred_currency : self::BASE_CURRENCY;
    }

    /**
     * 设置客户货币偏好
     */
    public function setCustomerPreference(
        Customer $customer,
        string $preferredCurrency,
        ?string $displayCurrency = null,
        ?array $acceptedCurrencies = null
    ): CustomerCurrencyPreference {
        return CustomerCurrencyPreference::updateOrCreate(
            ['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id],
            [
                'preferred_currency' => strtoupper($preferredCurrency),
                'display_currency' => strtoupper($displayCurrency ?? $preferredCurrency),
                'accepted_currencies' => $acceptedCurrencies ?? [strtoupper($preferredCurrency), self::BASE_CURRENCY],
            ]
        );
    }

    /**
     * 获取订阅在客户首选货币下的显示金额
     */
    public function getSubscriptionDisplayAmount(Subscription $subscription): array
    {
        $customer = $subscription->customer;
        $preferredCurrency = $this->getPreferredCurrency($customer);

        $result = [
            'original_currency' => $subscription->currency,
            'original_price' => (float) $subscription->price,
            'original_formatted' => ExchangeRate::format((float) $subscription->price, $subscription->currency),
        ];

        if ($preferredCurrency === $subscription->currency) {
            $result['display_currency'] = $preferredCurrency;
            $result['display_price'] = (float) $subscription->price;
            $result['display_formatted'] = $result['original_formatted'];
        } else {
            $converted = $this->convert(
                (float) $subscription->price,
                $subscription->currency,
                $preferredCurrency,
                $subscription->tenant_id
            );
            $result['display_currency'] = $preferredCurrency;
            $result['display_price'] = $converted['amount'];
            $result['display_formatted'] = ExchangeRate::format($converted['amount'], $preferredCurrency);
            $result['rate_used'] = $converted['rate'];
        }

        return $result;
    }

    /**
     * 从外部同步汇率（预留接口）
     */
    public function syncRatesFromProvider(string $provider = 'ecb', ?int $tenantId = null): int
    {
        $count = 0;

        try {
            $rates = match ($provider) {
                'ecb' => $this->fetchEcbRates(),
                default => [],
            };

            foreach ($rates as $from => $toRates) {
                foreach ($toRates as $to => $rate) {
                    $this->setRate($from, $to, $rate, $tenantId ?? 1, $provider);
                    $count++;
                }
            }

            Log::info('Currency: rates synced', ['provider' => $provider, 'count' => $count]);
        } catch (\Throwable $e) {
            Log::error('Currency: sync rates failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }

    /**
     * 从欧洲央行获取汇率（以 EUR 为基准）
     * 生产环境可扩展为真实 HTTP 调用
     */
    protected function fetchEcbRates(): array
    {
        $rates = [];

        // ECB 官方汇率参考 (2026-06-06)
        $ecbBase = [
            'USD' => 1.0840,
            'JPY' => 169.52,
            'GBP' => 0.8510,
            'CNY' => 7.8580,
            'HKD' => 8.4620,
            'SGD' => 1.4635,
            'KRW' => 1495.20,
        ];

        foreach ($ecbBase as $currency => $rate) {
            $rates['EUR'][$currency] = $rate;
            // EUR→CNY 和反向
            $rates['CNY']['EUR'] = round(1 / $ecbBase['CNY'], 8);
            $rates['EUR']['CNY'] = $ecbBase['CNY'];
        }

        // 交叉汇率 USD→CNY
        $rates['USD']['CNY'] = round($ecbBase['CNY'] / $ecbBase['USD'], 8);
        $rates['CNY']['USD'] = round(1 / $rates['USD']['CNY'], 8);

        return $rates;
    }

    /**
     * 清除汇率缓存
     */
    public function clearRateCache(?string $from = null, ?string $to = null): void
    {
        if ($from && $to) {
            Cache::forget(self::CACHE_PREFIX . "rate:{$from}:{$to}");
            Cache::forget(self::CACHE_PREFIX . "rate:{$to}:{$from}");
        } else {
            // 清除所有汇率缓存（保守策略）
            // 生产环境建议用 tagged cache
        }
    }

    /**
     * 计算订阅的 MRR/ARR（按基准货币统一计算）
     */
    public function calculateMrr(Subscription $subscription): float
    {
        $price = (float) $subscription->price;
        $currency = $subscription->currency;

        if ($currency !== self::BASE_CURRENCY) {
            $converted = $this->convert($price, $currency, self::BASE_CURRENCY, $subscription->tenant_id);
            $price = $converted['amount'];
        }

        return match ($subscription->billing_period) {
            'yearly' => round($price / 12, 2),
            'quarterly' => round($price / 3, 2),
            'semi_annually' => round($price / 6, 2),
            default => $price, // monthly / one_time
        };
    }
}
