<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperStickerPack
 */
class StickerPack extends Model
{
    protected $fillable = ['name', 'description', 'cover_url', 'user_id', 'is_system', 'sort_order'];

    public function stickers()
    {
        return $this->hasMany(Sticker::class, 'sticker_pack_id')->orderBy('sort_order');
    }
}
