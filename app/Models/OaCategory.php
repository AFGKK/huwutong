<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OaCategory extends Model
{
    protected $table = 'oa_categories';

    protected $fillable = [
        'name', 'icon', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function accounts()
    {
        return $this->hasMany(OfficialAccount::class, 'category_id');
    }
}
