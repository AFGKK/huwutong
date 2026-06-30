<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\HasTags, \App\Models\Concerns\TrackDataLineage;

    protected $fillable = [
        'tenant_id', 'product_id', 'customer_id', 'subscription_id', 'license_key',
        'type', 'status', 'activated_at', 'expires_at',
        'seats', 'max_devices', 'metadata',
        'watermark_key', 'signature_version', 'integrity_hash',
        'pool_mode', 'pool_timeout_minutes', 'pool_waiting_limit',
    ];

    /**
     * 数据血缘追踪配置
     */
    protected function lineageConfig(): array
    {
        return [
            'trackable_type' => 'license',
            'category' => 'license_key',
            'sensitivity' => 'confidential',
            'label' => fn($m) => 'License #' . $m->id . ' (' . ($m->license_key ? substr($m->license_key, 0, 12) . '...' : 'N/A') . ')',
            'fields' => [
                'license_key' => 'License Key',
                'status' => '状态',
                'type' => '类型',
                'expires_at' => '过期时间',
                'seats' => '席位',
                'max_devices' => '最大设备数',
                'pool_mode' => '池模式',
            ],
        ];
    }

    /**
     * 序列化时隐藏的字段
     */
    protected $hidden = [
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'seats' => 'integer',
            'max_devices' => 'integer',
            'pool_timeout_minutes' => 'integer',
            'pool_waiting_limit' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function seatAssignments()
    {
        return $this->hasMany(SeatAssignment::class);
    }

    public function activeSeatAssignments()
    {
        return $this->hasMany(SeatAssignment::class)->where('status', 'active');
    }

    public function waitingQueue()
    {
        return $this->hasMany(SeatWaitingQueue::class);
    }

    public function watermark()
    {
        return $this->hasOne(\App\Models\LicenseWatermark::class, 'license_id', 'id');
    }

    public function verificationLogs()
    {
        return $this->hasMany(\App\Models\LicenseVerificationLog::class, 'license_id');
    }

    public function tamperEvents()
    {
        return $this->hasMany(\App\Models\TamperEvent::class, 'license_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function notes()
    {
        return $this->hasMany(\App\Models\LicenseNote::class);
    }

    public function customFieldValues()
    {
        return $this->hasMany(\App\Models\LicenseCustomFieldValue::class);
    }

    public function customFields()
    {
        return $this->morphMany(\App\Models\CustomFieldValue::class, 'fieldable');
    }

    /**
     * SKU 关联 — 通过 metadata.sku_id
     */
    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'id', 'id')
            ->whereRaw('? = product_skus.id', [$this->metadata['sku_id'] ?? null])
            ->limit(1);
    }

    /**
     * 获取关联的 SKU（通过 metadata.sku_id）
     */
    public function getSku(): ?ProductSku
    {
        $skuId = $this->metadata['sku_id'] ?? null;
        if (!$skuId) return null;
        return ProductSku::find($skuId);
    }

    /**
     * 获取 SKU 交付物列表
     */
    public function getDeliverables(): array
    {
        $sku = $this->getSku();
        return $sku?->deliverables ?? [];
    }
}
