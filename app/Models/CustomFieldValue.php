<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 通用自定义字段值（多态关联 License/Customer/Product）
 *
 * @mixin IdeHelperCustomFieldValue
 */
class CustomFieldValue extends Model
{
    protected $table = 'custom_field_values';

    protected $fillable = [
        'field_definition_id',
        'fieldable_id',
        'fieldable_type',
        'value',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'field_definition_id');
    }

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }
}
