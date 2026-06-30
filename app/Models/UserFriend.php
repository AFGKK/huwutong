<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFriend extends Model
{
    protected $fillable = ['requester_id', 'addressee_id', 'status', 'remark'];
    protected function casts(): array { return ['status' => 'string']; }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function addressee(): BelongsTo { return $this->belongsTo(User::class, 'addressee_id'); }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }
}
