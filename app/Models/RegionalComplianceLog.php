<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperRegionalComplianceLog
 */
class RegionalComplianceLog extends Model
{
    protected $table = 'regional_compliance_logs';

    protected $fillable = [
        'tenant_id', 'region_key', 'action_type',
        'status', 'description', 'details',
        'performed_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
