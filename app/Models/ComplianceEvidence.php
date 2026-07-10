<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 合规证据项
 *
 * 自动化收集的证据，用于支持 SOC2/ISO27001 审计。
 * 证据类型包括：配置快照、日志样本、访问控制列表、
 * 加密设置、备份记录、渗透测试报告等。
 *
 * @m3-69 CompliancePack
 * @mixin IdeHelperComplianceEvidence
 */
class ComplianceEvidence extends Model
{
    protected $fillable = [
        'framework_code',
        'control_ref',
        'evidence_type',
        'title',
        'description',
        'source',
        'content',
        'file_path',
        'file_size',
        'mime_type',
        'collected_at',
        'collected_by',
        'status',
        'validated_by',
        'validated_at',
        'expires_at',
        'tags',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'validated_at' => 'datetime',
            'expires_at' => 'datetime',
            'tags' => 'array',
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * 范围：按框架
     */
    public function scopeFramework($query, string $code)
    {
        return $query->where('framework_code', $code);
    }

    /**
     * 范围：按控制域
     */
    public function scopeControl($query, string $ref)
    {
        return $query->where('control_ref', $ref);
    }

    /**
     * 范围：已验证
     */
    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }
}
