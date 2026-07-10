<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperTag
 */
class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'group',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    // ─── Scopes ───

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('slug', 'like', "%{$term}%");
        });
    }

    // ─── Helpers ───

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $base = $slug;
        $counter = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    public static function getOrCreate(string $name, ?string $group = null, ?string $color = null): self
    {
        $slug = Str::slug($name);
        return static::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'group' => $group,
                'color' => $color ?? '#909399',
            ]
        );
    }

    // ─── Relations ───

    public function licenses(): MorphToMany
    {
        return $this->morphedByMany(License::class, 'taggable');
    }

    public function tickets(): MorphToMany
    {
        return $this->morphedByMany(Ticket::class, 'taggable');
    }

    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'taggable');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }
}
