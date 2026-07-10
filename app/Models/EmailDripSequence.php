<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperEmailDripSequence
 */
class EmailDripSequence extends Model
{
    protected $table = 'email_drip_sequences';

    protected $fillable = [
        'campaign_id', 'name', 'delay_days', 'subject', 'content',
        'template_id', 'sort_order', 'ab_test', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ab_test' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo { return $this->belongsTo(EmailDripCampaign::class, 'campaign_id'); }
    public function recipients(): HasMany { return $this->hasMany(EmailDripRecipient::class, 'sequence_id'); }
}
