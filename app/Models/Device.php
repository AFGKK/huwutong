<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;
    protected $fillable = [
        'tenant_id', 'license_id', 'fingerprint', 'platform',
        'os_version', 'trust_score', 'is_blacklisted', 'is_virtual',
        'metadata', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'trust_score' => 'integer',
            'is_blacklisted' => 'boolean',
            'is_virtual' => 'boolean',
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
