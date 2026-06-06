<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KbArticle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'author_id', 'title', 'slug', 'content',
        'excerpt', 'tags', 'status', 'view_count',
        'helpful_count', 'unhelpful_count', 'locale',
        'related_article_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'view_count' => 'integer',
            'helpful_count' => 'integer',
            'unhelpful_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KbArticle $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(6);
            }
            if (empty($article->excerpt)) {
                $article->excerpt = Str::limit(strip_tags($article->content), 200);
            }
        });

        static::saving(function (KbArticle $article) {
            if ($article->isDirty('status') && $article->status === 'published' && empty($article->published_at)) {
                $article->published_at = now();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function relatedArticle(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'related_article_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KbArticleVersion::class, 'article_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(KbFeedback::class, 'article_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('content', 'like', "%{$term}%")
              ->orWhereJsonContains('tags', $term);
        });
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function recordView(): void
    {
        $this->increment('view_count');
    }

    public function getSatisfactionRate(): float
    {
        $total = $this->helpful_count + $this->unhelpful_count;
        if ($total === 0) {
            return 0;
        }
        return round(($this->helpful_count / $total) * 100, 1);
    }

    /**
     * 创建新版本
     */
    public function createVersion(string $changeSummary = null): KbArticleVersion
    {
        $maxVersion = $this->versions()->max('version_number') ?? 0;

        return $this->versions()->create([
            'author_id' => auth()->id(),
            'version_number' => $maxVersion + 1,
            'content' => $this->content,
            'change_summary' => $changeSummary,
        ]);
    }

    /**
     * 获取适合向量化的文本块
     */
    public function getChunks(int $maxLength = 1000): array
    {
        $chunks = [];
        $lines = explode("\n", $this->content);
        $current = '';
        $chunkIndex = 0;

        foreach ($lines as $line) {
            if (mb_strlen($current . $line) > $maxLength && !empty($current)) {
                $chunks[] = [
                    'chunk_index' => $chunkIndex,
                    'text' => trim($current),
                    'title' => $this->title,
                    'article_id' => $this->id,
                ];
                $chunkIndex++;
                $current = $line;
            } else {
                $current .= "\n" . $line;
            }
        }

        if (!empty(trim($current))) {
            $chunks[] = [
                'chunk_index' => $chunkIndex,
                'text' => trim($current),
                'title' => $this->title,
                'article_id' => $this->id,
            ];
        }

        return $chunks;
    }
}
