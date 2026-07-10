<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperDomainRoute
 */
class DomainRoute extends Model
{
    protected $fillable = [
        'custom_domain_id', 'type', 'target_url', 'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function customDomain()
    {
        return $this->belongsTo(CustomDomain::class);
    }
}
