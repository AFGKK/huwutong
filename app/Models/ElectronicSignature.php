<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperElectronicSignature
 */
class ElectronicSignature extends Model
{
    protected $fillable = [
        'signable_type', 'signable_id', 'user_id',
        'signature_hash', 'signature_data', 'status', 'type',
        'sequence', 'ip_address', 'remark', 'signed_at', 'expires_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeSigned($q)
    {
        return $q->where('status', 'signed');
    }
}
