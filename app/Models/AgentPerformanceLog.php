<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPerformanceLog extends Model
{
    protected $fillable = ['user_id', 'log_date', 'conversations_count', 'messages_count', 'avg_response_seconds', 'satisfaction_score', 'handoffs_count'];
    protected function casts(): array { return ['log_date' => 'date', 'satisfaction_score' => 'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
