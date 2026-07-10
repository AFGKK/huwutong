<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperE2eeIdentityKey
 */
class E2eeIdentityKey extends Model
{
    protected $fillable = ['user_id', 'public_key', 'signed_prekey', 'signature'];
    protected $table = 'e2ee_identity_keys';

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
