<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaFollowerTagRelation
 */
class OaFollowerTagRelation extends Model
{
    protected $table = 'oa_follower_tag_relations';

    protected $fillable = [
        'tag_id',
        'follower_id',
    ];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(OaFollowerTag::class, 'tag_id');
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(Follow::class, 'follower_id');
    }
}
