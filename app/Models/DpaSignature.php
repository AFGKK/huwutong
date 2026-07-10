<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DPA 签署记录 (M3-33)
 *
 * @mixin IdeHelperDpaSignature
 */
class DpaSignature extends Model
{
    protected $fillable = [
        'dpa_id',
        'tenant_id',
        'signed_by',
        'signer_name',
        'signer_title',
        'ip_address',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function dpa()
    {
        return $this->belongsTo(DataProcessingAgreement::class, 'dpa_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
