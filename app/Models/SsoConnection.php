<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSsoConnection
 */
class SsoConnection extends Model
{
    protected $fillable = [
        'user_id', 'sso_provider_id', 'external_id',
        'external_email', 'external_name', 'raw_attributes',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_attributes' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SsoProvider::class, 'sso_provider_id');
    }
}
