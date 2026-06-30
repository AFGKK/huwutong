<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductTranslation extends Model
{
    protected $fillable = [
        'translatable_type', 'translatable_id', 'locale', 'field',
        'value', 'is_auto_translated',
    ];

    protected function casts(): array
    {
        return [
            'is_auto_translated' => 'boolean',
        ];
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
