<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLicenseBatchGeneration
 */
class LicenseBatchGeneration extends Model
{
    protected $table = 'license_batch_generations';

    protected $fillable = [
        'tenant_id', 'user_id', 'license_template_id', 'name',
        'total_count', 'success_count', 'failed_count',
        'status', 'variable_values', 'override_rules',
        'generated_license_ids', 'error_message',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'variable_values' => 'array',
            'override_rules' => 'array',
            'generated_license_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'processing', 'completed', 'failed', 'partial'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function template(): BelongsTo { return $this->belongsTo(LicenseTemplate::class, 'license_template_id'); }
    public function items(): HasMany { return $this->hasMany(LicenseBatchItem::class, 'batch_generation_id'); }
}
