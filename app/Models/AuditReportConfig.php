<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAuditReportConfig
 */
class AuditReportConfig extends Model
{
    protected $table = 'audit_report_configs';

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'report_type',
        'config', 'is_shared', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_shared' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    const TYPES = ['trend', 'distribution', 'top_list', 'anomaly', 'custom'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
