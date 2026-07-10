<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLoginAudit
 */
class LoginAudit extends Model
{
    protected $fillable = [
        'user_id', 'email', 'action', 'ip_address', 'user_agent',
        'provider', 'success', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
