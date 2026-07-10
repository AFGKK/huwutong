<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperContentModeration
 */
class ContentModeration extends Model
{
    protected $fillable = [
        'moderatable_type', 'moderatable_id',
        'quality_score', 'moderation_status', 'reason',
        'action_taken', 'details', 'moderated_by', 'moderated_at',
    ];

    protected $casts = [
        'details' => 'array',
        'quality_score' => 'decimal:2',
        'moderated_at' => 'datetime',
    ];

    public function moderatable(): MorphTo
    {
        return $this->morphTo();
    }
}
