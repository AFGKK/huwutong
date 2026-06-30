<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumPollOption extends Model
{
    protected $fillable = ['poll_id', 'label', 'sort_order'];
    protected $table = 'forum_poll_options';

    public function poll(): BelongsTo { return $this->belongsTo(ForumPoll::class, 'poll_id'); }
    public function votes(): HasMany { return $this->hasMany(ForumPollVote::class, 'option_id'); }
}
