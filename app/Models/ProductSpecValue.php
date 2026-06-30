<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecValue extends Model
{
    protected $fillable = [
        'product_id', 'spec_id', 'value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function spec(): BelongsTo
    {
        return $this->belongsTo(ProductSpec::class, 'spec_id');
    }

    /**
     * 获取格式化后的值（附带单位）
     */
    public function formattedValue(): string
    {
        $spec = $this->spec;
        if (!$spec) return $this->value ?? '-';

        return match ($spec->type) {
            'boolean' => $this->value === '1' || $this->value === 'true' ? '✓' : '✗',
            default => empty($this->value) ? '-' : ($spec->unit ? $this->value . ' ' . $spec->unit : $this->value),
        };
    }
}
