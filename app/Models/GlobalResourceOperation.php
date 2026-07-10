<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperGlobalResourceOperation
 */
class GlobalResourceOperation extends Model
{
    protected $fillable = [
        'operation', 'resource_type', 'resource_id',
        'user_id', 'user_role', 'payload',
        'ip_address', 'allowed', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'payload' => 'array',
            'allowed' => 'boolean',
        ];
    }
}
