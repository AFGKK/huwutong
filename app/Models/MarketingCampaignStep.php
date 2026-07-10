<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMarketingCampaignStep
 */
class MarketingCampaignStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'step_order', 'action_type',
        'config', 'delay_type', 'delay_minutes', 'conditions',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'conditions' => 'array',
            'delay_minutes' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    const ACTION_TYPES = [
        'send_email' => '发送邮件',
        'send_sms' => '发送短信',
        'send_notification' => '发送站内信',
        'wait' => '等待',
        'condition' => '条件分支',
        'segment' => '细分筛选',
    ];

    const DELAY_TYPES = [
        'immediate' => '立即',
        'delay' => '延迟',
        'schedule' => '定时',
    ];
}
