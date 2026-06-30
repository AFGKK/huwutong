<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FriendGroup extends Model
{
    protected $fillable = ['user_id', 'name', 'sort_order'];
    protected function casts(): array { return ['sort_order' => 'integer']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
