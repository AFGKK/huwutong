<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperKbFeedback
 */
class KbFeedback extends Model
{
    protected $fillable = [
        'article_id', 'is_helpful', 'comment', 'session_id',
    ];

    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class);
    }
}
