<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 代理商等级变更历史 (M3-04)
 *
 * @mixin IdeHelperAgentTierHistory
 */
class AgentTierHistory extends Model
{
    protected $fillable = [
        'agent_id', 'from_level', 'to_level',
        'reason', 'remark', 'operated_by',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operated_by');
    }
}
