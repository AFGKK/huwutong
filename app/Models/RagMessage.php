<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'role', 'content',
        'source_documents', 'confidence', 'token_count',
        'response_time_ms', 'was_helpful',
    ];

    protected $table = 'rag_messages';

    protected function casts(): array
    {
        return [
            'source_documents' => 'array',
            'confidence' => 'float',
            'token_count' => 'integer',
            'response_time_ms' => 'float',
            'was_helpful' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RagConversation::class, 'conversation_id');
    }
}
