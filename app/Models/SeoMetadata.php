<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMetadata extends Model
{
    use HasFactory;

    protected $table = 'seo_metadata';

    protected $fillable = [
        'seoable_type', 'seoable_id', 'tenant_id',
        'meta_title', 'meta_description', 'meta_keywords',
        'canonical_url',
        'og_title', 'og_description', 'og_image',
        'robots', 'priority', 'change_frequency', 'json_ld',
    ];

    protected function casts(): array
    {
        return [
            'json_ld' => 'array',
            'priority' => 'string',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
