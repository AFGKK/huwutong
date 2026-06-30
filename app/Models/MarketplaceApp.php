<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceApp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'developer_id', 'slug', 'name', 'short_description', 'description',
        'category', 'icon_url', 'screenshots', 'demo_video_url',
        'status', 'pricing_type', 'price',
        'install_count', 'avg_rating', 'review_count', 'webhook_url',
        'permissions', 'documentation_url', 'repository_url',
        'license_info', 'privacy_url', 'support_url',
        'current_version',
        'review_notes', 'reviewed_by', 'reviewed_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'screenshots' => 'array',
            'price' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceDeveloper::class, 'developer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MarketplaceAppVersion::class, 'app_id');
    }

    public function reviewLogs(): HasMany
    {
        return $this->hasMany(MarketplaceAppReviewLog::class, 'app_id');
    }

    public function installations(): HasMany
    {
        return $this->hasMany(MarketplaceAppInstallation::class, 'app_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MarketplaceAppReview::class, 'app_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(MarketplaceAppReview::class, 'app_id')->where('status', 'approved');
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(MarketplaceDownloadLog::class, 'app_id');
    }

    public function rollouts(): HasMany
    {
        return $this->hasMany(MarketplaceAppRollout::class, 'app_id');
    }

    public function activeRollout(): HasMany
    {
        return $this->hasMany(MarketplaceAppRollout::class, 'app_id')->where('status', 'active');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
