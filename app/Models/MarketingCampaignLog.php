<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'step_id', 'customer_id',
        'channel', 'recipient', 'status', 'error_message',
        'message_id', 'ab_variant',
        'sent_at', 'delivered_at', 'opened_at', 'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignStep::class, 'step_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    const CHANNELS = ['email', 'sms', 'in_app'];

    const STATUSES = [
        'pending' => '待发送',
        'sent' => '已发送',
        'delivered' => '已送达',
        'opened' => '已打开',
        'clicked' => '已点击',
        'converted' => '已转化',
        'bounced' => '退回',
        'failed' => '失败',
    ];
}
