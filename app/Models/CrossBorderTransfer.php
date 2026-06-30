<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 跨境数据传输评估 (PIPL Art.38)
 *
 * 记录跨境数据传输的目的地、法律依据、安全措施
 */
class CrossBorderTransfer extends Model
{
    const LEGAL_CONSENT = 'consent';           // 单独同意
    const LEGAL_STANDARD_CLAUSES = 'standard_clauses'; // 标准合同
    const LEGAL_ADEQUACY = 'adequacy';         // 充分保护认定
    const LEGAL_SAFE_HARBOR = 'safe_harbor';   // 安全港
    const LEGAL_OTHER = 'other';

    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'data_category',
        'recipient_country',
        'recipient_name',
        'recipient_purpose',
        'transfer_method',
        'legal_basis',
        'security_measures',
        'impact_assessment',
        'status',
        'reviewed_at',
        'next_review_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
