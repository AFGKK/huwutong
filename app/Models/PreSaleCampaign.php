<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperPreSaleCampaign
 */
class PreSaleCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'type', 'name', 'slug', 'description', 'images',
        'product_id', 'target_amount', 'min_amount', 'raised_amount',
        'target_backers', 'current_backers',
        'deposit_rate', 'deposit_amount', 'currency',
        'start_at', 'end_at', 'estimated_delivery_at',
        'status', 'tiers', 'settings', 'fail_reason',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'target_amount' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'raised_amount' => 'decimal:2',
            'deposit_rate' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'target_backers' => 'integer',
            'current_backers' => 'integer',
            'tiers' => 'array',
            'settings' => 'array',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
        ];
    }

    const TYPES = ['pre_sale', 'crowdfunding'];
    const STATUSES = ['draft', 'pending', 'active', 'success', 'failed', 'cancelled', 'completed'];

    protected static function booted(): void
    {
        static::creating(function (self $campaign) {
            if (empty($campaign->slug)) {
                $campaign->slug = Str::slug($campaign->name) . '-' . Str::random(6);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PreSaleOrder::class, 'campaign_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PreSaleUpdate::class, 'campaign_id');
    }

    // 范围
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // 计算属性
    public function getProgressPercentAttribute(): float
    {
        if (!$this->target_amount || $this->target_amount <= 0) return 0;
        return min(100, round($this->raised_amount / $this->target_amount * 100, 1));
    }

    public function getRemainingDaysAttribute(): int
    {
        return max(0, now()->diffInDays($this->end_at, false));
    }

    public function getIsPreSaleAttribute(): bool
    {
        return $this->type === 'pre_sale';
    }

    public function getIsCrowdfundingAttribute(): bool
    {
        return $this->type === 'crowdfunding';
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->start_at <= now()
            && $this->end_at >= now();
    }

    public function hasEnded(): bool
    {
        return $this->end_at < now();
    }

    public function hasReachedTarget(): bool
    {
        if (!$this->target_amount || $this->target_amount <= 0) return true;
        return $this->raised_amount >= $this->target_amount;
    }
}
