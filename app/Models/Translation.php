<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTranslation
 */
class Translation extends Model
{
    protected $fillable = [
        'namespace_id',
        'locale',
        'key',
        'value',
        'default_value',
        'is_published',
        'is_auto_translated',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_auto_translated' => 'boolean',
        ];
    }

    public function namespace(): BelongsTo
    {
        return $this->belongsTo(TranslationNamespace::class, 'namespace_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TranslationHistory::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    public function scopeNamespace($query, string $namespace)
    {
        return $query->whereHas('namespace', fn ($q) => $q->where('namespace', $namespace));
    }

    public function recordHistory(string $action, ?string $oldValue, ?string $newValue, ?int $userId = null): void
    {
        $this->histories()->create([
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'action' => $action,
            'user_id' => $userId,
        ]);
    }
}
