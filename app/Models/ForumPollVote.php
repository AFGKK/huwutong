<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperForumPollVote
 */
class ForumPollVote extends Model
{
    protected $fillable = ['poll_id', 'option_id', 'user_id'];
    protected $table = 'forum_poll_votes';

    public function poll(): BelongsTo { return $this->belongsTo(ForumPoll::class, 'poll_id'); }
    public function option(): BelongsTo { return $this->belongsTo(ForumPollOption::class, 'option_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
