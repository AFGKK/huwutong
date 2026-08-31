<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperBillingCycle
 */
class BillingCycle extends Model
{
    protected $fillable = [
        'code', 'name', 'months', 'days', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取所有启用周期编码列表（用于验证规则）
     */
    public static function activeCodes(): array
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    /**
     * 获取选项列表（用于前端下拉）
     */
    public static function options(): array
    {
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'months', 'days'])
            ->toArray();
    }

    /**
     * 根据 code 和起始日期计算结束日期
     * 支持 months + days 组合（如 1个月5天）
     */
    public static function calculateEndDate(string $code, \Carbon\Carbon $startDate): \Carbon\Carbon
    {
        $cycle = self::resolvePeriod($code);
        if (!$cycle) {
            return match ($code) {
                'yearly' => $startDate->copy()->addYear(),
                'quarterly' => $startDate->copy()->addMonths(3),
                'semi_annually' => $startDate->copy()->addMonths(6),
                default => $startDate->copy()->addMonth(),
            };
        }
        return $cycle->applyTo($startDate);
    }

    /**
     * 按周期编码解析 BillingCycle 记录
     */
    public static function resolvePeriod(string $code): ?self
    {
        return self::where('code', $code)->where('is_active', true)->first()
            ?? self::where('code', $code)->first();
    }

    /**
     * 将周期应用到指定日期，返回新的结束日期
     */
    public function applyTo(\Carbon\Carbon $startDate): \Carbon\Carbon
    {
        $end = $startDate->copy();

        if ($this->months) {
            $end = $end->addMonths((int) $this->months);
        }
        if ($this->days) {
            $end = $end->addDays((int) $this->days);
        }

        return $end;
    }
}
