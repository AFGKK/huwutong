<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Device;
use App\Models\Invoice;
use App\Policies\SubscriptionPolicy;
use App\Policies\TicketPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\DevicePolicy;
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Subscription::class => SubscriptionPolicy::class,
        Ticket::class => TicketPolicy::class,
        Customer::class => CustomerPolicy::class,
        Product::class => ProductPolicy::class,
        Device::class => DevicePolicy::class,
        Invoice::class => InvoicePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
