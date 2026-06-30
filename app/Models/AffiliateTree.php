<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 多级推广关系链 (M3-05)
 *
 * 记录代理之间的上下级关系及分成比例。
 */
class AffiliateTree extends Model
{
    protected $table = 'affiliate_tree';

    protected $fillable = [
        'parent_agent_id', 'child_agent_id', 'level',
        'rate', 'status', 'attributed_at',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'attributed_at' => 'datetime',
    ];

    public function parentAgent()
    {
        return $this->belongsTo(Agent::class, 'parent_agent_id');
    }

    public function childAgent()
    {
        return $this->belongsTo(Agent::class, 'child_agent_id');
    }
}
