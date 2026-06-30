<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbAutoGrowDraft extends Model
{
    protected $fillable = [
        'title', 'content', 'excerpt', 'tags',
        'source_type', 'source_id', 'source_summary',
        'confidence', 'status',
        'kb_article_id', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'confidence' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function kbArticle(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeBySource($q, string $type)
    {
        return $q->where('source_type', $type);
    }
}
