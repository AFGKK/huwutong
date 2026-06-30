<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, \App\Models\Concerns\HasTags, \App\Models\Concerns\TrackDataLineage;

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'level', 'status',
        'lifecycle_stage', 'stage_entered_at',
        'prepaid_balance', 'credit_limit', 'credit_used', 'billing_method',
        'merged_into_customer_id', 'merge_count',
    ];

    /**
     * 数据血缘追踪配置
     */
    protected function lineageConfig(): array
    {
        return [
            'trackable_type' => 'customer',
            'category' => 'pii',
            'sensitivity' => 'confidential',
            'label' => fn($m) => '客户 #' . $m->id . ' (' . ($m->user?->name ?? $m->user?->email ?? 'N/A') . ')',
            'fields' => [
                'type' => '客户类型',
                'level' => '客户等级',
                'status' => '状态',
                'lifecycle_stage' => '生命周期阶段',
                'prepaid_balance' => '预付余额',
                'credit_limit' => '信用额度',
                'billing_method' => '计费方式',
            ],
        ];
    }

    protected function casts(): array
    {
        return [
            'prepaid_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'credit_used' => 'decimal:2',
            'merge_count' => 'integer',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // ─── M3-56 预付余额 & 信用系统 ───

    public function prepaidBalance()
    {
        return $this->hasOne(PrepaidBalance::class);
    }

    public function prepaidTransactions()
    {
        return $this->hasMany(PrepaidTransaction::class);
    }

    public function creditLimit()
    {
        return $this->hasOne(CreditLimit::class);
    }

    public function getAvailableBalanceAttribute(): float
    {
        return (float) ($this->prepaid_balance ?? 0);
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, (float) ($this->credit_limit ?? 0) - (float) ($this->credit_used ?? 0));
    }

    public function customFields()
    {
        return $this->morphMany(\App\Models\CustomFieldValue::class, 'fieldable');
    }
}
