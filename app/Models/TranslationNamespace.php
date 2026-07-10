<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTranslationNamespace
 */
class TranslationNamespace extends Model
{
    use HasFactory;
    protected $table = 'translation_namespaces';

    protected $fillable = [
        'namespace',
        'label',
        'description',
        'key_count',
    ];

    protected function casts(): array
    {
        return [
            'key_count' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class, 'namespace_id');
    }
}
