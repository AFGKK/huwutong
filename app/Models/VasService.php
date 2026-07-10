<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperVasService
 */
class VasService extends Model
{
    use HasFactory;

    protected $table = 'vas_services';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'description', 'category',
        'price_monthly', 'price_yearly', 'currency',
        'billing_mode', 'metered_config', 'features', 'limits',
        'is_public', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metered_config' => 'array',
            'features' => 'array',
            'limits' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    const CATEGORIES = [
        'feature' => '功能增强',
        'support' => '技术支持',
        'storage' => '存储空间',
        'api' => 'API 调用',
        'ai' => 'AI 服务',
    ];

    const BILLING_MODES = ['flat', 'usage', 'tiered'];
    const BILLING_MODE_LABELS = [
        'flat' => '固定价格',
        'usage' => '按量计费',
        'tiered' => '阶梯定价',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VasSubscription::class, 'vas_service_id');
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(VasSubscription::class, 'vas_service_id')->where('status', 'active');
    }
}
