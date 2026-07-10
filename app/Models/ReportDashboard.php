<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperReportDashboard
 */
class ReportDashboard extends Model
{
    protected $table = 'report_dashboards';

    protected $fillable = [
        'user_id', 'tenant_id', 'name', 'description',
        'layout', 'is_default', 'is_shared', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'is_default' => 'boolean',
            'is_shared' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
