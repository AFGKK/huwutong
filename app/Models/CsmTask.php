<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CsmTask extends Model
{
    use SoftDeletes;

    protected $table = 'csm_tasks';

    protected $fillable = [
        'tenant_id', 'customer_id', 'assigned_to',
        'title', 'description', 'priority', 'status',
        'category', 'related_type', 'related_id',
        'metadata', 'due_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    const PRIORITIES = ['low' => '低', 'normal' => '普通', 'high' => '高', 'urgent' => '紧急'];
    const STATUSES = ['open' => '待处理', 'in_progress' => '进行中', 'completed' => '已完成', 'cancelled' => '已取消'];
    const CATEGORIES = ['renewal' => '续费', 'onboarding' => '上手', 'support' => '支持', 'review' => '回顾', 'checkin' => '回访', 'custom' => '自定义'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
