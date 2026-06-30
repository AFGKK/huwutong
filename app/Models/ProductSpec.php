<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpec extends Model
{
    protected $fillable = [
        'spec_group_id', 'label', 'type', 'unit', 'options', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductSpecGroup::class, 'spec_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductSpecValue::class, 'spec_id');
    }
}
