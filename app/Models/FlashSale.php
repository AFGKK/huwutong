<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlashSale extends Model
{
    use SoftDeletes;

    protected $table = 'flash_sales';

    protected $fillable = [
        'tenant_id', 'sku_id', 'name', 'flash_price', 'original_price',
        'stock', 'max_per_user', 'start_time', 'end_time', 'status', 'preheat_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'preheat_at' => 'datetime',
        ];
    }

    public function sku(): BelongsTo { return $this->belongsTo(ProductSku::class, 'sku_id'); }
    public function orders(): HasMany { return $this->hasMany(FlashSaleOrder::class, 'flash_sale_id'); }
}
