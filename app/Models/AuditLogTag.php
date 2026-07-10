<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperAuditLogTag
 */
class AuditLogTag extends Model
{
    protected $table = 'audit_log_tags';

    protected $fillable = [
        'name', 'color',
    ];

    public function logs(): BelongsToMany
    {
        return $this->belongsToMany(Log::class, 'audit_log_tag_log', 'tag_id', 'log_id');
    }
}
