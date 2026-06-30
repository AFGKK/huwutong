<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaArticleShare extends Model
{
    protected $fillable = ['article_id', 'user_id', 'platform'];
    protected $table = 'oa_article_shares';

    public function article(): BelongsTo { return $this->belongsTo(OaArticle::class, 'article_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
