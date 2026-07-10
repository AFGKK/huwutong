<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAuditAnalysisSummary
 */
class AuditAnalysisSummary extends Model
{
    protected $table = 'audit_analysis_summaries';

    protected $fillable = [
        'tenant_id', 'summary_date', 'period', 'type', 'action',
        'count', 'unique_users', 'unique_ips', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
