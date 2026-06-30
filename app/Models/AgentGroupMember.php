<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentGroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'role'];
    public function group(): BelongsTo { return $this->belongsTo(AgentGroup::class, 'group_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
