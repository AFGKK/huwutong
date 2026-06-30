<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseTemplateVariable extends Model
{
    protected $table = 'license_template_variables';

    protected $fillable = [
        'license_template_id', 'key', 'label', 'variable_type',
        'options', 'default_value', 'description', 'is_required', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    const TYPES = ['string', 'number', 'date', 'boolean', 'select'];

    public function template(): BelongsTo { return $this->belongsTo(LicenseTemplate::class, 'license_template_id'); }
}
