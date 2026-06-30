<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificationLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'level_order',
        'icon_url', 'color', 'passing_score', 'requirements', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'is_active' => 'boolean',
            'level_order' => 'integer',
            'passing_score' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'certification_level_id');
    }

    public function developerCertifications(): HasMany
    {
        return $this->hasMany(DeveloperCertification::class, 'certification_level_id');
    }

    public function activeQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'certification_level_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
