<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCrmEntityMapping
 */
class CrmEntityMapping extends Model
{
    protected $table = 'crm_entity_mappings';

    protected $fillable = [
        'tenant_id', 'provider', 'entity_type', 'local_id',
        'remote_id', 'remote_url', 'last_synced_at', 'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
