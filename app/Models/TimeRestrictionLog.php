<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeRestrictionLog extends Model
{
    protected $fillable = [
        'config_id',
        'license_id',
        'result',
        'reason',
        'ip_address',
        'timezone_used',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function config()
    {
        return $this->belongsTo(TimeRestrictionConfig::class, 'config_id');
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
