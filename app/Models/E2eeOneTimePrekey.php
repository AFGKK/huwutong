<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class E2eeOneTimePrekey extends Model
{
    protected $fillable = ['user_id', 'key_id', 'public_key', 'is_used', 'used_at'];
    protected $casts = ['is_used' => 'boolean', 'used_at' => 'datetime'];
    protected $table = 'e2ee_one_time_prekeys';

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
