<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RenewalPipelineService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class RenewalPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected RenewalPipelineService $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = $this->app->make(RenewalPipelineService::class);
    }

    public function test_handle_renewal_failure_creates_attempt(): void
    {
        $subscription = $this->createSubscription();

        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'invoice_no' => 'INV-TEST-' . uniqid(),
            'amount' => 99.00,
            'currency' => 'CNY',
            'status' => 'pending',
            'billing_reason' => 'subscription_renew',
            'due_at' => now()->addDays(7),
        ]);

        $attempt = $this->pipeline->handleRenewalFailure(
            $subscription,
            $invoice,
            'insufficient_funds',
            'Card declined: insufficient funds'
        );

        $this->assertNotNull($attempt);
        $this->assertEquals(1, $attempt->attempt_number);
        $this->assertEquals('failed', $attempt->status);
        $this->assertEquals('insufficient_funds', $attempt->failure_reason);
        $this->assertFalse($attempt->escalated);
        $this->assertNotNull($attempt->next_retry_at);
    }

    public function test_multiple_failures_downgrade_after_threshold(): void
    {
        $subscription = $this->createSubscription(['plan' => 'Pro', 'price' => 299]);

        // Simulate 3 failures
        for ($i = 0; $i < 3; $i++) {
            $invoice = Invoice::create([
                'tenant_id' => $subscription->tenant_id,
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'invoice_no' => 'INV-TEST-' . uniqid(),
                'amount' => 299.00,
                'currency' => 'CNY',
                'status' => 'pending',
                'billing_reason' => 'subscription_renew',
                'due_at' => now()->addDays(7),
            ]);

            $this->pipeline->handleRenewalFailure(
                $subscription,
                $invoice,
                'payment_failed',
                "Attempt $i failed"
            );
        }

        $stats = $this->pipeline->getFailureStats();
        $this->assertEquals(3, $stats['total_failures']);

        // After 3 failures, plan should be downgraded from Pro to Basic
        $subscription->refresh();
        $this->assertEquals('Basic', $subscription->plan);
    }

    public function test_get_failure_stats_returns_accurate_counts(): void
    {
        $stats = $this->pipeline->getFailureStats();
        $this->assertArrayHasKey('total_attempts', $stats);
        $this->assertArrayHasKey('total_failures', $stats);
        $this->assertArrayHasKey('failure_rate', $stats);
        $this->assertEquals(0, $stats['failure_rate']);
    }

    public function test_renewal_attempts_retry_plan(): void
    {
        $subscription = $this->createSubscription();

        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'invoice_no' => 'INV-TEST-' . uniqid(),
            'amount' => 99.00,
            'currency' => 'CNY',
            'status' => 'pending',
            'billing_reason' => 'subscription_renew',
            'due_at' => now()->addDays(7),
        ]);

        $attempt = $this->pipeline->handleRenewalFailure(
            $subscription,
            $invoice,
            'payment_failed'
        );

        $this->assertNotNull($attempt->retry_plan);
        $this->assertCount(4, $attempt->retry_plan); // 5 max - 1 current attempt = 4 remaining

        $firstRetry = $attempt->retry_plan[0];
        $this->assertEquals(2, $firstRetry['attempt']);
        $this->assertEquals('retry', $firstRetry['action']);
    }

    protected function createSubscription(array $overrides = []): Subscription
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test-' . uniqid() . '.example.com',
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'type' => 'software',
        ]);

        return Subscription::create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'active',
            'plan' => 'Pro',
            'price' => 99.00,
            'currency' => 'CNY',
            'billing_period' => 'monthly',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'grace_days' => 7,
            'next_billing_at' => now(),
            'last_billed_at' => now()->subMonth(),
            'billing_cycles_completed' => 1,
            'total_paid' => 99.00,
        ], $overrides));
    }
}
