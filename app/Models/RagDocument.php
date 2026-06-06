<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RagDocument extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'title', 'content',
        'metadata', 'embedding', 'locale',
    ];

    protected $table = 'rag_documents';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'embedding' => 'array',
        ];
    }

    public function scopeBySource($query, string $type, ?int $id = null)
    {
        $query->where('source_type', $type);
        if ($id !== null) {
            $query->where('source_id', $id);
        }
        return $query;
    }
}
