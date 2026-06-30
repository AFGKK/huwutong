<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelCategory extends Model
{
    protected $fillable = ['name', 'sort_order'];
    protected $table = 'channel_categories';

    public function channels(): HasMany { return $this->hasMany(Channel::class, 'category_id'); }
}
