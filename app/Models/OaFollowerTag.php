<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOaFollowerTag
 */
class OaFollowerTag extends Model
{
    protected $table = 'oa_follower_tags';

    protected $fillable = [
        'account_id',
        'name',
        'color',
        'sort_order',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'account_id');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(OaFollowerTagRelation::class, 'tag_id');
    }
}
