<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客户支付方式模型
 *
 * 存储脱敏的支付方式信息，支持多卡管理和默认支付方式。
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id',
        'gateway', 'method_type', 'gateway_method_id',
        'last_four', 'card_brand', 'cardholder_name',
        'expiry_month', 'expiry_year',
        'billing_zip', 'billing_country',
        'is_default', 'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 将指定支付方式设为默认
     */
    public function setAsDefault(): void
    {
        // 取消该用户的所有默认标记
        static::where('customer_id', $this->customer_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
