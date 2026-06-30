<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'status', 'type',
        'audience_type', 'segment_id', 'audience_filter',
        'scheduled_at', 'started_at', 'ended_at', 'timezone',
        'channel_config',
        'is_ab_test', 'ab_test_metric', 'ab_test_split', 'ab_test_variants',
        'target_count', 'sent_count', 'delivered_count',
        'opened_count', 'clicked_count', 'converted_count',
        'bounced_count', 'unsubscribed_count',
        'budget', 'cost_spent',
        'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array',
            'channel_config' => 'array',
            'ab_test_variants' => 'array',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'budget' => 'decimal:2',
            'cost_spent' => 'decimal:2',
            'is_ab_test' => 'boolean',
            'ab_test_split' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class, 'segment_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MarketingCampaignStep::class, 'campaign_id')->orderBy('step_order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MarketingCampaignLog::class, 'campaign_id');
    }

    const STATUSES = [
        'draft' => '草稿',
        'active' => '进行中',
        'paused' => '已暂停',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ];

    const TYPES = [
        'email' => '邮件营销',
        'sms' => '短信营销',
        'in_app' => '站内信',
        'multi_channel' => '多渠道',
    ];

    const AUDIENCE_TYPES = [
        'all' => '所有客户',
        'segment' => '客户细分',
        'custom' => '自定义筛选',
    ];

    const AB_METRICS = [
        'open_rate' => '打开率',
        'click_rate' => '点击率',
        'conversion_rate' => '转化率',
    ];
}
