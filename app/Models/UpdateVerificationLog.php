<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-15 更新签名验证日志
 */
class UpdateVerificationLog extends Model
{
    protected $fillable = [
        'update_package_id', 'sdk_instance_id', 'tenant_id',
        'algorithm', 'verified', 'file_hash', 'expected_hash',
        'signature', 'error_message', 'client_ip', 'user_agent',
    ];

    protected $casts = [
        'verified' => 'boolean',
    ];

    public function package(): BelongsTo { return $this->belongsTo(UpdatePackage::class, 'update_package_id'); }

    public function scopeVerified($q) { return $q->where('verified', true); }
    public function scopeFailed($q) { return $q->where('verified', false); }
}
