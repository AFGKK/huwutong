<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAgentGroup
 */
class AgentGroup extends Model
{
    protected $fillable = ['name', 'description', 'color', 'sort_order', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean', 'sort_order' => 'integer']; }

    public function members(): HasMany { return $this->hasMany(AgentGroupMember::class, 'group_id'); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'agent_group_members', 'group_id', 'user_id')->withPivot('role'); }
}
