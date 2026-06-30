<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GraphQLService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQLServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GraphQLService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GraphQLService::class);
        $this->tenant = Tenant::factory()->create();
    }

    /** @test */
    public function it_returns_schema_with_all_types()
    {
        $schema = $this->service->getSchema();

        $this->assertArrayHasKey('Tenant', $schema);
        $this->assertArrayHasKey('User', $schema);
        $this->assertArrayHasKey('Customer', $schema);
        $this->assertArrayHasKey('Product', $schema);
        $this->assertArrayHasKey('License', $schema);
        $this->assertArrayHasKey('Device', $schema);
        $this->assertArrayHasKey('Subscription', $schema);
        $this->assertArrayHasKey('Invoice', $schema);
    }

    /** @test */
    public function it_returns_error_for_unknown_type()
    {
        $result = $this->service->execute(['type' => 'UnknownType']);

        $this->assertNull($result['data']);
        $this->assertNotNull($result['errors']);
    }

    /** @test */
    public function it_can_query_licenses_by_type()
    {
        $product = Product::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        License::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'type' => 'enterprise',
            'status' => 'active',
        ]);

        License::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'type' => 'professional',
            'status' => 'active',
        ]);

        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id', 'license_key', 'type', 'status'],
            'args' => [
                'filter' => ['type' => 'enterprise'],
                'per_page' => 20,
            ],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertNull($result['errors']);
        $this->assertCount(3, $result['data']['data']);
        $this->assertEquals('enterprise', $result['data']['data'][0]['type']);
    }

    /** @test */
    public function it_supports_pagination()
    {
        $product = Product::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        License::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
        ]);

        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id', 'license_key'],
            'args' => ['page' => 1, 'per_page' => 2],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertCount(2, $result['data']['data']);
        $this->assertEquals(5, $result['data']['paginatorInfo']['total']);
        $this->assertEquals(2, $result['data']['paginatorInfo']['perPage']);
        $this->assertTrue($result['data']['paginatorInfo']['hasMorePages']);
    }

    /** @test */
    public function it_can_query_single_record_by_id()
    {
        $product = Product::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'license_key' => 'HWT-UNIQUE-TEST',
        ]);

        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id', 'license_key', 'status'],
            'args' => ['id' => $license->id],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertEquals($license->id, $result['data']['id']);
        $this->assertEquals('HWT-UNIQUE-TEST', $result['data']['license_key']);
    }

    /** @test */
    public function it_returns_error_for_nonexistent_single_record()
    {
        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id'],
            'args' => ['id' => 99999],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertNotNull($result['errors']);
    }

    /** @test */
    public function it_can_query_customers_with_filters()
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        Customer::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'type' => 'business',
        ]);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'type' => 'individual',
        ]);

        $result = $this->service->execute([
            'type' => 'Customer',
            'fields' => ['id', 'type', 'status'],
            'args' => ['filter' => ['type' => 'business']],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertCount(2, $result['data']['data']);
    }

    /** @test */
    public function it_can_query_products()
    {
        Product::factory()->count(3)->create();
        Product::factory()->create(['is_active' => false]);

        $result = $this->service->execute([
            'type' => 'Product',
            'fields' => ['id', 'name', 'slug', 'is_active'],
            'args' => ['filter' => ['is_active' => true], 'per_page' => 20],
        ]);

        $this->assertCount(3, $result['data']['data']);
    }

    /** @test */
    public function it_supports_sorting()
    {
        $product = Product::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'type' => 'enterprise',
            'created_at' => now()->subDays(2),
        ]);

        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'type' => 'basic',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id', 'type'],
            'args' => [
                'sort' => [['field' => 'created_at', 'direction' => 'asc']],
                'per_page' => 20,
            ],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertCount(2, $result['data']['data']);
        $this->assertEquals('enterprise', $result['data']['data'][0]['type']);
    }

    /** @test */
    public function it_validates_filter_fields()
    {
        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id'],
            'args' => ['filter' => ['nonexistent_field' => 'test']],
        ], ['tenant_id' => $this->tenant->id]);

        // Invalid filter should be silently ignored
        $this->assertNull($result['errors']);
    }

    /** @test */
    public function it_does_not_expose_sensitive_fields()
    {
        $fields = $this->service->getDefaultFields('User');

        $this->assertContains('id', $fields);
        $this->assertNotContains('password', $fields);
    }

    /** @test */
    public function it_can_execute_batch_queries()
    {
        $product = Product::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        License::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
        ]);

        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user2->id,
        ]);

        $results = $this->service->executeBatch([
            'licenses' => [
                'type' => 'License',
                'fields' => ['id', 'license_key'],
                'args' => ['per_page' => 20, 'filter' => []],
            ],
            'customers' => [
                'type' => 'Customer',
                'fields' => ['id', 'type'],
                'args' => ['per_page' => 20, 'filter' => []],
            ],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertArrayHasKey('licenses', $results['data']);
        $this->assertArrayHasKey('customers', $results['data']);
        $this->assertCount(2, $results['data']['licenses']['data']['data']);
        $this->assertCount(1, $results['data']['customers']['data']['data']);
    }

    /** @test */
    public function it_enforces_tenant_isolation()
    {
        $tenant2 = Tenant::factory()->create();
        $product = Product::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // License in tenant 1
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
        ]);

        // License in tenant 2
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        License::factory()->create([
            'tenant_id' => $tenant2->id,
            'product_id' => $product->id,
        ]);

        // Query from tenant 1 context
        $result = $this->service->execute([
            'type' => 'License',
            'fields' => ['id', 'license_key'],
            'args' => ['per_page' => 20],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertCount(1, $result['data']['data']);

        // With explicit filter override
        $result2 = $this->service->execute([
            'type' => 'License',
            'fields' => ['id'],
            'args' => ['per_page' => 20, 'filter' => ['tenant_id' => $tenant2->id]],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertCount(1, $result2['data']['data']);
    }

    /** @test */
    public function it_has_schema_with_all_type_definitions()
    {
        $schema = $this->service->getSchema();

        foreach (['Tenant', 'User', 'Customer', 'Product', 'License', 'Device', 'Subscription', 'Invoice'] as $type) {
            $this->assertArrayHasKey('fields', $schema[$type]);
            $this->assertArrayHasKey('relations', $schema[$type]);
            $this->assertArrayHasKey('filters', $schema[$type]);
            $this->assertArrayHasKey('sortable', $schema[$type]);

            $this->assertGreaterThan(0, count($schema[$type]['fields']),
                "{$type} should have at least one field");
        }
    }

    /** @test */
    public function it_handles_related_entity_queries()
    {
        $product = Product::factory()->create(['name' => 'GraphQL Test Product']);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
        ]);

        // Query license with related product (using with eager loading hint)
        $license->load('product');

        $result = $this->service->execute([
            'type' => 'License',
            'fields' => [
                'id',
                'license_key',
                'product' => [
                    'type' => 'Product',
                    'fields' => ['id', 'name', 'slug'],
                ],
            ],
            'args' => ['id' => $license->id],
        ], ['tenant_id' => $this->tenant->id]);

        $this->assertEquals($license->id, $result['data']['id']);
        $this->assertArrayHasKey('product', $result['data']);
    }
}
