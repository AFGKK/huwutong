<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperOaArticleEmbedding
 */
class OaArticleEmbedding extends Model
{
    protected $fillable = ['article_id', 'embedding'];
    protected $casts = ['embedding' => 'array'];
    protected $table = 'oa_article_embeddings';

    public function article()
    {
        return $this->belongsTo(OaArticle::class);
    }
}
