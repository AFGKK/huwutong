<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaArticleRead extends Model
{
    protected $fillable = ['article_id', 'user_id', 'ip'];
    protected $table = 'oa_article_reads';

    public function article(): BelongsTo { return $this->belongsTo(OaArticle::class, 'article_id'); }
}
