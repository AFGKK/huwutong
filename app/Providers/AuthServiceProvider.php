<?php

namespace App\Providers;

use App\Models\CorsConfig;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\LicenseTemplate;
use App\Models\Refund;
use App\Policies\CorsConfigPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DevicePolicy;
use App\Policies\HandoffRequestPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ProductPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TagPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\LicenseTemplatePolicy;
use App\Policies\RefundPolicy;
use App\Models\KbCategory;
use App\Policies\KbCategoryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CorsConfig::class => CorsConfigPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Tag::class => TagPolicy::class,
        Ticket::class => TicketPolicy::class,
        Customer::class => CustomerPolicy::class,
        Product::class => ProductPolicy::class,
        Device::class => DevicePolicy::class,
        Invoice::class => InvoicePolicy::class,
        HandoffRequest::class => HandoffRequestPolicy::class,
        User::class => UserPolicy::class,
        LicenseTemplate::class => LicenseTemplatePolicy::class,
        Refund::class => RefundPolicy::class,
        KbCategory::class => KbCategoryPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
