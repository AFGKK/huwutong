<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'product_id', 'customer_id', 'subscription_id', 'license_key',
        'type', 'status', 'activated_at', 'expires_at',
        'seats', 'max_devices', 'metadata',
    ];

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
            'metadata' => 'array',
        ];
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
}
