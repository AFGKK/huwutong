<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 整体测试报告 v4 安全问题回归
 */
class SecurityV4RegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected Product $product;
    protected User $admin;
    protected User $plainUser;
    protected Customer $otherCustomer;
    protected License $otherLicense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->otherTenant = Tenant::factory()->create(['name' => 'Tenant B Spinka']);
        $this->product = Product::factory()->create();

        $adminRole = Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'email' => 'admin_v4@test.local',
        ]);
        $this->plainUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'email' => 'plain_v4@test.local',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('model_has_roles')->insert([
            'role_id' => $adminRole->id,
            'model_type' => User::class,
            'model_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->load('roles');

        $this->otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        $this->otherLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'customer_id' => $this->otherCustomer->id,
            'license_key' => 'HWT-1EC09E1570C7EAF8ACEA',
            'status' => 'pending',
        ]);
    }

    public function test_plain_user_cannot_list_admin_tenants(): void
    {
        Sanctum::actingAs($this->plainUser, ['*']);

        $response = $this->getJson('/api/admin/tenants');

        $response->assertForbidden();
    }

    public function test_plain_user_license_list_does_not_leak_others_keys(): void
    {
        Sanctum::actingAs($this->plainUser, ['*']);

        $response = $this->getJson('/api/licenses');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('HWT-1EC09E1570C7EAF8ACEA', $content);

        $data = $response->json('data') ?? [];
        foreach ($data as $row) {
            $key = $row['license_key'] ?? '';
            if ($key !== '') {
                $this->assertStringContainsString('*', $key);
            }
            $this->assertNotEquals($this->otherCustomer->id, $row['customer_id'] ?? null);
        }
    }

    public function test_plain_user_cannot_create_license(): void
    {
        Sanctum::actingAs($this->plainUser, ['*']);

        $response = $this->postJson('/api/licenses', [
            'product_id' => $this->product->id,
            'type' => 'standard',
        ]);

        $response->assertForbidden();
        $this->assertStringNotContainsString('Undefined array key', $response->getContent());
        $this->assertStringNotContainsString('SYS_INTERNAL_ERROR', $response->getContent());
    }

    public function test_admin_can_list_tenants_scoped(): void
    {
        Sanctum::actingAs($this->admin, ['admin', 'super-admin']);

        $response = $this->getJson('/api/admin/tenants');

        $response->assertOk();
        $ids = collect($response->json('data') ?? [])->pluck('id')->all();
        $this->assertContains($this->tenant->id, $ids);
        $this->assertNotContains($this->otherTenant->id, $ids);
    }
}
