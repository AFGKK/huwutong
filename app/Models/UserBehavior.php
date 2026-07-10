<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUserBehavior
 */
class UserBehavior extends Model
{
    use HasFactory;

    protected $table = 'user_behaviors';

    protected $fillable = [
        'tenant_id', 'user_id', 'customer_id',
        'event_type', 'event_action', 'resource_type', 'resource_id',
        'session_id', 'page_url', 'referrer', 'user_agent',
        'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    const EVENT_TYPES = [
        'page_view' => '页面浏览',
        'feature_use' => '功能使用',
        'license_action' => 'License操作',
        'purchase' => '购买行为',
        'login' => '登录',
        'search' => '搜索',
        'api_call' => 'API调用',
        'support_ticket' => '工单行为',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
