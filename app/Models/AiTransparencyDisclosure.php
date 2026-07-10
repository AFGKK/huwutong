<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAiTransparencyDisclosure
 */
class AiTransparencyDisclosure extends Model
{
    protected $fillable = [
        'ai_system_id', 'locale', 'disclosure_text', 'disclosure_type',
        'is_active', 'effective_from',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
    ];

    public function system(): BelongsTo { return $this->belongsTo(AiSystemRegistry::class, 'ai_system_id'); }
}
