<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteChannelDailyStat extends Model
{
    protected $table = 'invite_channel_daily_stats';

    protected $fillable = [
        'channel_id', 'stat_date',
        'impressions', 'clicks', 'registrations', 'conversions',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'extra_data' => 'array',
        ];
    }

    public function channel(): BelongsTo { return $this->belongsTo(InviteChannel::class); }
}
