<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VasSubscription extends Model
{
    use HasFactory;

    protected $table = 'vas_subscriptions';

    protected $fillable = [
        'tenant_id', 'vas_service_id', 'subscription_id', 'customer_id',
        'status', 'start_date', 'end_date', 'billing_period',
        'price', 'currency', 'applied_features', 'usage_limits',
        'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cancelled_at' => 'datetime',
            'price' => 'decimal:2',
            'applied_features' => 'array',
            'usage_limits' => 'array',
        ];
    }

    const STATUSES = ['active', 'suspended', 'cancelled', 'expired'];
    const STATUS_LABELS = [
        'active' => '使用中',
        'suspended' => '已暂停',
        'cancelled' => '已取消',
        'expired' => '已过期',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vasService(): BelongsTo
    {
        return $this->belongsTo(VasService::class, 'vas_service_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
