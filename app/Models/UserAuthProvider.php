<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserAuthProvider
 */
class UserAuthProvider extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_id',
        'avatar', 'nickname', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
