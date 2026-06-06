<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    protected $fillable = [
        'license_id', 'device_id', 'ip_address',
        'fingerprint', 'action', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
