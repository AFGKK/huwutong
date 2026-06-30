<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseCustomFieldValue extends Model
{
    protected $fillable = [
        'license_id', 'field_definition_id', 'value',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'field_definition_id');
    }
}
