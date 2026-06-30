<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScimResourceMapping extends Model
{
    protected $fillable = [
        'tenant_id', 'scim_config_id', 'resource_type',
        'external_id', 'external_user_name', 'internal_id', 'status',
    ];

    public function scimConfig(): BelongsTo
    {
        return $this->belongsTo(ScimConfig::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function internalUser()
    {
        return $this->belongsTo(User::class, 'internal_id');
    }
}
