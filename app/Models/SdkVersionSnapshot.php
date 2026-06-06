<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SDK 版本分布快照
 */
class SdkVersionSnapshot extends Model
{
    protected $fillable = [
        'tenant_id', 'sdk_language', 'sdk_version',
        'instance_count', 'snapshot_date',
    ];

    protected function casts(): array
    {
        return [
            'instance_count' => 'integer',
            'snapshot_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
