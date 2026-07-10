<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperForumExpLog
 */
class ForumExpLog extends Model
{
    protected $table = 'forum_exp_logs';
    protected $fillable = ['user_id', 'amount', 'reason', 'exp_before', 'exp_after', 'level_before', 'level_after'];
}
