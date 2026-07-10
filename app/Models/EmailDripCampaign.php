<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperEmailDripCampaign
 */
class EmailDripCampaign extends Model
{
    use SoftDeletes;

    protected $table = 'email_drip_campaigns';

    protected $fillable = [
        'tenant_id', 'name', 'trigger_event', 'status', 'description',
        'target_filters', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sequences(): HasMany { return $this->hasMany(EmailDripSequence::class, 'campaign_id')->orderBy('sort_order'); }
    public function recipients(): HasMany { return $this->hasMany(EmailDripRecipient::class, 'campaign_id'); }
}
