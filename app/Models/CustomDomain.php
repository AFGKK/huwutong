<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'domain', 'cname_target', 'verification_method',
        'verification_value', 'verified', 'verified_at', 'is_active',
        'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sslCertificate()
    {
        return $this->hasOne(SslCertificate::class);
    }

    public function domainRoute()
    {
        return $this->hasOne(DomainRoute::class);
    }
}
