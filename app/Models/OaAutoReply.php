<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaAutoReply extends Model
{
    protected $fillable = [
        'account_id',
        'type',
        'keyword',
        'match_type',
        'content',
        'content_type',
        'media_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'match_type' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'account_id');
    }
}
