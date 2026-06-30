<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionalComplianceConfig extends Model
{
    protected $table = 'regional_compliance_configs';

    protected $fillable = [
        'tenant_id', 'region_key', 'region_name',
        'gdpr_enabled', 'pipl_enabled', 'vat_enabled',
        'data_residency_enabled', 'cookie_consent_enabled', 'tax_reporting_enabled',
        'tax_type', 'tax_rate', 'tax_reporting_frequency',
        'digital_service_tax', 'oss_enabled', 'oss_threshold',
        'currency', 'timezone', 'languages',
        'is_active', 'options',
    ];

    protected function casts(): array
    {
        return [
            'gdpr_enabled' => 'boolean',
            'pipl_enabled' => 'boolean',
            'vat_enabled' => 'boolean',
            'data_residency_enabled' => 'boolean',
            'cookie_consent_enabled' => 'boolean',
            'tax_reporting_enabled' => 'boolean',
            'digital_service_tax' => 'boolean',
            'oss_enabled' => 'boolean',
            'tax_rate' => 'decimal:2',
            'oss_threshold' => 'decimal:2',
            'languages' => 'array',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 获取此区域的合规要求列表
     */
    public function getActiveRequirements(): array
    {
        $requirements = [];
        $map = [
            'gdpr_enabled' => 'GDPR',
            'pipl_enabled' => 'PIPL',
            'vat_enabled' => 'VAT/GST',
            'data_residency_enabled' => '数据本地化',
            'cookie_consent_enabled' => 'Cookie 同意',
            'tax_reporting_enabled' => '税务申报',
        ];
        foreach ($map as $field => $label) {
            if ($this->$field) {
                $requirements[] = $label;
            }
        }
        return $requirements;
    }
}
