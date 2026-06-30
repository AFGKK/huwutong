<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomReport extends Model
{
    protected $table = 'custom_reports';

    protected $fillable = [
        'user_id', 'tenant_id', 'name', 'slug', 'description', 'category',
        'data_source', 'metrics', 'dimensions', 'filters', 'sorts',
        'chart_type', 'chart_options',
        'is_template', 'is_shared', 'is_scheduled',
        'schedule_cron', 'schedule_recipients', 'export_format',
        'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'dimensions' => 'array',
            'filters' => 'array',
            'sorts' => 'array',
            'chart_options' => 'array',
            'schedule_recipients' => 'array',
            'is_template' => 'boolean',
            'is_shared' => 'boolean',
            'is_scheduled' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }

    const CATEGORIES = ['financial', 'license', 'customer', 'audit', 'custom'];
    const DATA_SOURCES = ['subscriptions', 'invoices', 'licenses', 'customers', 'activations', 'churn', 'audit_logs'];
    const CHART_TYPES = ['table', 'bar', 'line', 'pie', 'area', 'radar', 'number'];
    const EXPORT_FORMATS = ['csv', 'xlsx', 'pdf'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class, 'report_id');
    }

    public function latestSnapshot()
    {
        return $this->hasOne(ReportSnapshot::class, 'report_id')->latestOfMany();
    }
}
