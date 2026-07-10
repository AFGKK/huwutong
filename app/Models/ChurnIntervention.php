<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperChurnIntervention
 */
class ChurnIntervention extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'tenant_id', 'customer_id', 'type', 'title', 'description',
        'assigned_to', 'status', 'result', 'outcome',
        'scheduled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    const TYPES = [
        'renewal_call' => '续费电话',
        'coupon_offer' => '优惠券',
        'training_session' => '培训辅导',
        'executive_engagement' => '高层介入',
        'survey' => '满意度调研',
        'product_showcase' => '产品演示',
        'technical_support' => '技术支持',
    ];

    const STATUSES = [
        'pending' => '待处理',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ];

    const OUTCOMES = [
        'positive' => '积极',
        'neutral' => '中性',
        'negative' => '消极',
        'unknown' => '未知',
    ];
}
