<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseTemplateFieldMapping extends Model
{
    protected $table = 'license_template_field_mappings';

    protected $fillable = [
        'license_template_id', 'template_field', 'license_field', 'mapping_type',
    ];

    public function template(): BelongsTo { return $this->belongsTo(LicenseTemplate::class, 'license_template_id'); }
}
