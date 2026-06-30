<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OaCollection extends Model
{
    protected $table = 'oa_collections';
    protected $fillable = ['account_id', 'name', 'description', 'cover_image', 'sort_order'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'account_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(OaArticle::class, 'collection_id');
    }
}
