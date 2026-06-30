<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceAppReviewLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_id', 'reviewer_id', 'action', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class, 'app_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
