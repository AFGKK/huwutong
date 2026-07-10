<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperEmailDripRecipient
 */
class EmailDripRecipient extends Model
{
    protected $table = 'email_drip_recipients';

    protected $fillable = [
        'campaign_id', 'sequence_id', 'customer_id', 'email',
        'status', 'sent_at', 'opened_at', 'clicked_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo { return $this->belongsTo(EmailDripCampaign::class, 'campaign_id'); }
    public function sequence(): BelongsTo { return $this->belongsTo(EmailDripSequence::class, 'sequence_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
