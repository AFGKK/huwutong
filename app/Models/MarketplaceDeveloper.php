<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceDeveloper extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'display_name', 'company_name', 'website', 'description',
        'status', 'verified_at', 'verified_by',
        'earnings_account_id', 'commission_rate',
        'total_earned', 'total_withdrawn',
        'tax_id', 'tax_info',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'commission_rate' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'tax_info' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function apps(): HasMany
    {
        return $this->hasMany(MarketplaceApp::class, 'developer_id');
    }

    public function earningsAccount(): BelongsTo
    {
        return $this->belongsTo(EarningsAccount::class, 'earnings_account_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
