<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DPA 数据处理协议 (M3-33)
 *
 * 记录与租户之间的数据处理协议，包含数据类别、处理目的、子处理者、安全措施等。
 */
class DataProcessingAgreement extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id',
        'title',
        'version',
        'content',
        'status',
        'data_categories',
        'processing_purposes',
        'sub_processors',
        'security_measures',
        'jurisdiction',
        'effective_at',
        'expires_at',
    ];

    protected $casts = [
        'data_categories' => 'array',
        'processing_purposes' => 'array',
        'sub_processors' => 'array',
        'security_measures' => 'array',
        'effective_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function signatures()
    {
        return $this->hasMany(DpaSignature::class, 'dpa_id');
    }

    /**
     * 是否已被指定租户签署
     */
    public function isSignedByTenant(int $tenantId): bool
    {
        return $this->signatures()->where('tenant_id', $tenantId)->exists();
    }
}
