<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperLicenseTemplate
 */
class LicenseTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'description',
        'type',
        'seats',
        'max_devices',
        'expiry_days',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'max_devices' => 'integer',
            'expiry_days' => 'integer',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 计算过期时间（基于 expiry_days）
     */
    public function calculateExpiresAt(): ?string
    {
        if ($this->expiry_days === null) {
            return null;
        }
        return now()->addDays($this->expiry_days)->format('Y-m-d H:i:s');
    }

    /**
     * 应用模板创建 License 数据
     */
    public function apply(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->product_id,
            'type' => $this->type,
            'seats' => $this->seats,
            'max_devices' => $this->max_devices,
            'expires_at' => $this->calculateExpiresAt(),
            'metadata' => $this->metadata,
        ], $overrides);
    }
}
