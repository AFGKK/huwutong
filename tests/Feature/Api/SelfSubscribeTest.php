<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfSubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_subscribe_paid_plan_creates_pending_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $plan = PricingPlan::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'basic-test',
            'name' => '基础版测试',
            'price_monthly' => 99,
            'price_yearly' => 948,
            'is_active' => true,
            'is_public' => true,
            'trial_days' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/portal/billing/self-subscribe', [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'force_payment' => true,
            'auto_renew' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_payment', true)
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $user->customer->id,
            'pricing_plan_slug' => 'basic-test',
            'status' => 'pending',
        ]);

        $invoiceId = $response->json('data.invoice.id');
        $this->assertNotEmpty($invoiceId);

        $pay = $this->postJson("/api/portal/billing/invoices/{$invoiceId}/pay", [
            'payment_method' => 'mock',
        ]);

        $pay->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $response->json('data.subscription.id'),
            'status' => 'active',
        ]);
    }

    public function test_self_subscribe_free_plan_activates_immediately(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $plan = PricingPlan::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'free-test',
            'name' => '免费版测试',
            'price_monthly' => 0,
            'is_active' => true,
            'is_public' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/portal/billing/self-subscribe', [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_payment', false)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscriptions', [
            'pricing_plan_slug' => 'free-test',
            'status' => 'active',
        ]);
    }
}
