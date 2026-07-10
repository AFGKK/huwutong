<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTranslationImport
 */
class TranslationImport extends Model
{
    protected $table = 'translation_imports';

    protected $fillable = [
        'type',
        'format',
        'file_path',
        'summary',
        'status',
        'error_message',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
