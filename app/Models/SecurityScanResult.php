<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 安全扫描结果模型
 *
 * M2-112: 存储 OWASP ZAP 自动化渗透测试的扫描结果
 */
class SecurityScanResult extends Model
{
    protected $fillable = [
        'scan_type',
        'target_url',
        'high_count',
        'medium_count',
        'low_count',
        'passed',
        'alerts',
        'report_file',
        'executed_at',
    ];

    protected $casts = [
        'alerts' => 'array',
        'passed' => 'boolean',
        'executed_at' => 'datetime',
        'high_count' => 'integer',
        'medium_count' => 'integer',
        'low_count' => 'integer',
    ];

    public function scopeLatest($query, $column = 'created_at')
    {
        return $query->orderBy($column, 'desc');
    }
}
