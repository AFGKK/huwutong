<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTranslationHistory
 */
class TranslationHistory extends Model
{
    protected $table = 'translation_histories';

    protected $fillable = [
        'translation_id',
        'old_value',
        'new_value',
        'action',
        'user_id',
    ];

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
