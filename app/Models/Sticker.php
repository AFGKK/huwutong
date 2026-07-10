<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSticker
 */
class Sticker extends Model
{
    protected $fillable = ['sticker_pack_id', 'image_url', 'emoji', 'sort_order'];

    public function pack()
    {
        return $this->belongsTo(StickerPack::class, 'sticker_pack_id');
    }
}
