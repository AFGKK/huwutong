<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperCustomerSegment
 */
class CustomerSegment extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'rules', 'color', 'icon',
        'is_dynamic', 'is_active', 'member_count',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_dynamic' => 'boolean',
            'is_active' => 'boolean',
            'member_count' => 'integer',
        ];
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_segment_members')
            ->withTimestamps();
    }

    /**
     * 尝试匹配规则，判断客户是否属于此分群
     */
    public function matchesCustomer(Customer $customer): bool
    {
        if (! $this->is_dynamic || ! $this->rules) {
            return false;
        }

        $rules = $this->rules;

        // 类型匹配
        if (isset($rules['type']) && $customer->type !== $rules['type']) {
            return false;
        }

        // 等级匹配
        if (isset($rules['level']) && $customer->level !== $rules['level']) {
            return false;
        }

        // 状态匹配
        if (isset($rules['status']) && $customer->status !== $rules['status']) {
            return false;
        }

        // 标签匹配 (任意匹配)
        if (isset($rules['tags']) && is_array($rules['tags'])) {
            $customerTags = $customer->tags->pluck('name')->toArray();
            if (! array_intersect($rules['tags'], $customerTags)) {
                return false;
            }
        }

        // 最低订阅数
        if (isset($rules['min_subscriptions'])) {
            $count = $customer->subscriptions()->count();
            if ($count < $rules['min_subscriptions']) {
                return false;
            }
        }

        // 最高订阅数
        if (isset($rules['max_subscriptions'])) {
            $count = $customer->subscriptions()->count();
            if ($count > $rules['max_subscriptions']) {
                return false;
            }
        }

        return true;
    }
}
