<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSignature extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'content_hash',
        'signature', 'signing_key_id', 'content_preview', 'metadata', 'signed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'signed_at' => 'datetime',
    ];

    public function scopeBySource($q, string $type, int $id)
    {
        return $q->where('source_type', $type)->where('source_id', $id);
    }

    public function scopeByHash($q, string $hash)
    {
        return $q->where('content_hash', $hash);
    }
}
