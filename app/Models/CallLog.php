<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $fillable = [
        'caller_id', 'callee_id', 'conversation_id',
        'call_type', 'status', 'duration',
        'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
    ];

    public function caller(): BelongsTo { return $this->belongsTo(User::class, 'caller_id'); }
    public function callee(): BelongsTo { return $this->belongsTo(User::class, 'callee_id'); }
}
