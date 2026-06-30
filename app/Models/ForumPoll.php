<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumPoll extends Model
{
    protected $fillable = ['post_id', 'question', 'is_multiple', 'expires_at'];
    protected $casts = ['is_multiple' => 'boolean', 'expires_at' => 'datetime'];
    protected $table = 'forum_polls';

    public function post(): BelongsTo { return $this->belongsTo(ForumPost::class, 'post_id'); }
    public function options(): HasMany { return $this->hasMany(ForumPollOption::class, 'poll_id'); }
    public function votes(): HasMany { return $this->hasMany(ForumPollVote::class, 'poll_id'); }
}
