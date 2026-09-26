<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 回归：20260925 管理后台测试报告 P0/P1/P2/P3
 */
class AdminAccessControlRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $customer;
    private string $adminToken;
    private string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('admin', 'web');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('super-admin');

        $this->customer = User::factory()->create(['tenant_id' => null]);

        $this->adminToken = $this->admin->createToken('admin', $this->admin->tokenAbilities())->plainTextToken;
        $this->customerToken = $this->customer->createToken('customer', $this->customer->tokenAbilities())->plainTextToken;
    }

    public function test_customer_token_abilities_exclude_admin_wildcard(): void
    {
        $abilities = $this->customer->tokenAbilities();
        $this->assertContains('customer', $abilities);
        $this->assertNotContains('*', $abilities);
        $this->assertNotContains('admin', $abilities);
        $this->assertNotContains('super-admin', $abilities);

        $adminAbilities = $this->admin->tokenAbilities();
        $this->assertContains('admin', $adminAbilities);
        $this->assertContains('super-admin', $adminAbilities);
        $this->assertNotContains('*', $adminAbilities);
    }

    public function test_customer_cannot_access_admin_routes_with_ability_middleware(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->customerToken];

        $this->getJson('/api/admin/system-health/check', $headers)->assertStatus(403);
        $this->getJson('/api/admin/api-gateway/config', $headers)->assertStatus(403);
        $this->getJson('/api/admin/activity-feed', $headers)->assertStatus(403);
        $this->getJson('/api/admin/customer-api-keys', $headers)->assertStatus(403);
        $this->getJson('/api/admin/sessions', $headers)->assertStatus(403);
        $this->getJson('/api/admin/product-skus', $headers)->assertStatus(403);
        $this->getJson('/api/admin/security/dashboard', $headers)->assertStatus(403);
    }

    public function test_admin_can_access_previously_bare_admin_routes(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken];

        $this->getJson('/api/admin/activity-feed', $headers)->assertOk();
        $this->getJson('/api/admin/api-gateway/config', $headers)->assertOk();
    }

    public function test_customer_orders_list_does_not_leak_others(): void
    {
        Order::create([
            'order_no' => 'HWT-LEAK-'.uniqid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'total_amount' => 100,
            'final_amount' => 100,
            'currency' => 'CNY',
            'status' => Order::STATUS_CANCELLED,
        ]);

        $response = $this->getJson('/api/orders', [
            'Authorization' => 'Bearer '.$this->customerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $rows = is_array($data) && isset($data['data']) ? $data['data'] : ($data ?? []);
        $this->assertCount(0, $rows);
    }

    public function test_customer_refunds_list_does_not_500_or_leak_path(): void
    {
        $response = $this->getJson('/api/refunds', [
            'Authorization' => 'Bearer '.$this->customerToken,
        ]);

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('RefundWorkflowService', $body);
        $this->assertStringNotContainsString('phpEnv', $body);
        $this->assertStringNotContainsString('Argument #', $body);
    }

    public function test_double_prefix_admin_deletion_route_removed(): void
    {
        $uris = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->uri());

        $this->assertTrue($uris->contains('api/admin/deletion/records'));
        $this->assertFalse($uris->contains('api/api/admin/deletion/records'));
        $this->assertFalse($uris->contains('api/api/shop/products'));
        $this->assertFalse($uris->contains('api/api/changelog'));
        $this->assertTrue($uris->contains('api/shop/products'));
    }

    public function test_mfa_login_route_is_rate_limited(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/mfa/login' && in_array('POST', $r->methods()));

        $this->assertNotNull($route);
        $middleware = implode(',', $route->gatherMiddleware());
        $this->assertStringContainsString('throttle', $middleware);
    }
}