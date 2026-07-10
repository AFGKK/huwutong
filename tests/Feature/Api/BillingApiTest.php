<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Product $product;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->product = Product::factory()->create();
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 订阅 CRUD ───

    public function test_index_returns_paginated_subscriptions(): void
    {
        Subscription::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->getJson('/api/billing/subscriptions?per_page=3', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Laravel paginate() returns flat metadata alongside data[] in the JSON
        $responseData = $response->json('data');
        $this->assertArrayHasKey('current_page', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertIsArray($responseData['data']);
        $this->assertCount(3, $responseData['data']);
    }

    public function test_index_filters_by_status(): void
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'status' => 'grace',
        ]);

        $response = $this->getJson('/api/billing/subscriptions?status=grace', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_store_creates_subscription(): void
    {
        $plan = PricingPlan::factory()->create([
            'slug' => 'pro',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/billing/subscriptions', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'plan_slug' => $plan->slug,
            'billing_period' => 'monthly',
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
        $this->assertEquals($plan->slug, $response->json('data.plan'));
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/billing/subscriptions', [], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_subscription_detail(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->getJson("/api/billing/subscriptions/{$subscription->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $subscription->id);
    }

    // ─── 状态变更 ───

    public function test_change_plan(): void
    {
        $currentPlan = PricingPlan::factory()->create([
            'slug' => 'starter',
            'tenant_id' => $this->tenant->id,
        ]);
        $enterprisePlan = PricingPlan::factory()->create([
            'slug' => 'enterprise',
            'tenant_id' => $this->tenant->id,
        ]);

        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'plan' => $currentPlan->slug,
        ]);

        $response = $this->putJson(
            "/api/billing/subscriptions/{$subscription->id}/plan",
            ['plan_slug' => $enterprisePlan->slug],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan', $enterprisePlan->slug);
    }

    public function test_cancel_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->postJson(
            "/api/billing/subscriptions/{$subscription->id}/cancel",
            ['reason' => '价格太高'],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'cancellation_reason' => '价格太高',
        ]);
    }

    public function test_resume_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'canceled_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/billing/subscriptions/{$subscription->id}/resume",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'canceled_at' => null,
        ]);
    }

    public function test_renew_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->postJson(
            "/api/billing/subscriptions/{$subscription->id}/renew",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ─── 发票 ───

    public function test_invoice_list(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ]);

        Invoice::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->getJson('/api/billing/invoices', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    // ─── 统计 ───

    public function test_stats(): void
    {
        Subscription::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'price' => 100,
            'billing_period' => 'monthly',
        ]);

        $response = $this->getJson('/api/billing/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'active', 'mrr', 'estimated_arr']]);
    }

    // ─── 权限 ───

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/billing/subscriptions');

        $response->assertStatus(401);
    }
}
