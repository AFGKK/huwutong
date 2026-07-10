<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperGeoLookup
 */
class GeoLookup extends Model
{
    protected $primaryKey = 'ip_address';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ip_address', 'country_code', 'country_name',
        'city', 'latitude', 'longitude',
        'isp', 'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'cached_at' => 'datetime',
        ];
    }
}
