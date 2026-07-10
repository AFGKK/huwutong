<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperReadingQueue
 */
class ReadingQueue extends Model
{
    protected $table = 'reading_queue';
    protected $fillable = ['user_id', 'queueable_type', 'queueable_id', 'note', 'sort_order', 'is_completed', 'completed_at'];
    protected $casts = ['is_completed' => 'boolean', 'completed_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function queueable(): MorphTo { return $this->morphTo(); }
}
