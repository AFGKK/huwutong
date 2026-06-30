<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseDomainValidationLog extends Model
{
    protected $fillable = [
        'license_id',
        'domain',
        'result',
        'ip_address',
        'user_agent',
        'reason',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeBlocked($query)
    {
        return $query->where('result', 'blocked');
    }
}
