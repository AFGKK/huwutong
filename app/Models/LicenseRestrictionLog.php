<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $restrictable_type
 * @property int $restrictable_id
 * @property string $type ip_range / geo_fence
 * @property string $result allowed / blocked / audited
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $reason
 * @property array|null $context
 */
class LicenseRestrictionLog extends Model
{
    protected $fillable = [
        'restrictable_type',
        'restrictable_id',
        'type',
        'result',
        'ip_address',
        'country',
        'reason',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function restrictable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
