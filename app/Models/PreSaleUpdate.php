<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreSaleUpdate extends Model
{
    protected $fillable = [
        'campaign_id', 'title', 'content', 'type', 'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PreSaleCampaign::class, 'campaign_id');
    }
}
