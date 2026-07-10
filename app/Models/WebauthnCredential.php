<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperWebauthnCredential
 */
class WebauthnCredential extends Model
{
    protected $fillable = [
        'user_id', 'credential_id', 'public_key', 'type',
        'transport', 'client_id', 'aaguid', 'device_name',
        'counter', 'last_used_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'counter' => 'integer',
            'last_used_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
