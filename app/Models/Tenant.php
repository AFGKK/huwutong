<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'logo', 'domain', 'subscription_plan',
        'status', 'data_region', 'branding',
        'mfa_policy', 'allowed_ips',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'allowed_ips' => 'array',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function members()
    {
        return $this->hasMany(TenantMember::class);
    }

    public function webhookEvents()
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function customDomains()
    {
        return $this->hasMany(\App\Models\CustomDomain::class);
    }
}
