<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProductDemo
 */
class ProductDemo extends Model
{
    protected $fillable = [
        'product_id',
        'platform',
        'site_url',
        'account',
        'password',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
