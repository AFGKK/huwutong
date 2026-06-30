<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleHierarchy extends Model
{
    protected $table = 'role_hierarchy';

    protected $fillable = [
        'role_id',
        'parent_role_id',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }

    public function parentRole(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'parent_role_id');
    }
}
