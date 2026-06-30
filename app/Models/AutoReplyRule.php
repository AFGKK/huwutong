<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoReplyRule extends Model
{
    protected $fillable = ['name', 'trigger_type', 'trigger_value', 'match_mode', 'reply_content', 'agent_group_id', 'priority', 'match_count', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean', 'priority' => 'integer', 'match_count' => 'integer']; }

    public function agentGroup(): BelongsTo { return $this->belongsTo(AgentGroup::class); }
}
