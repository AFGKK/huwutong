<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLicenseComplianceReport
 */
class LicenseComplianceReport extends Model
{
    protected $table = 'license_compliance_reports';

    protected $fillable = [
        'tenant_id', 'customer_id', 'title', 'type', 'format',
        'status', 'filters', 'summary_data',
        'file_path', 'file_name', 'file_size',
        'report_period_start', 'report_period_end',
        'generated_at', 'downloaded_at', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'summary_data' => 'array',
            'report_period_start' => 'datetime',
            'report_period_end' => 'datetime',
            'generated_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public const TYPES = [
        'full_inventory' => '完整 License 清单',
        'activation_audit' => '激活使用审计',
        'compliance_summary' => '合规摘要报告',
        'custom' => '自定义报告',
    ];

    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isReady(): bool
    {
        return $this->status === 'completed' && $this->file_path;
    }
}
