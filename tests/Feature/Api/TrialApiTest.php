<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_create_trial_successfully(): void
    {
        $response = $this->postJson('/api/trial', [
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['license', 'trial_days', 'expires_at']]);
    }

    public function test_create_trial_validates_required_fields(): void
    {
        $response = $this->postJson('/api/trial', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_create_trial_rejects_duplicate(): void
    {
        // 首次创建
        $this->postJson('/api/trial', [
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ])->assertStatus(201);

        // 再次创建（同一个 customer 不能重复试用）
        $response = $this->postJson('/api/trial', [
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TRIAL_NOT_ALLOWED');
    }

    public function test_check_trial_status(): void
    {
        $create = $this->postJson('/api/trial', [
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $licenseId = $create->json('data.license.id');

        $response = $this->getJson("/api/trial/{$licenseId}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['status', 'license_key', 'expires_at']]);
    }

    public function test_check_nonexistent_trial(): void
    {
        $response = $this->getJson('/api/trial/99999');

        $response->assertStatus(404);
    }

    public function test_convert_trial_to_paid(): void
    {
        $create = $this->postJson('/api/trial', [
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $licenseId = $create->json('data.license.id');

        $response = $this->postJson("/api/trial/{$licenseId}/convert", [
            'type' => 'standard',
            'days' => 365,
            'max_devices' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.license.type', 'standard');
    }

    public function test_convert_trial_with_invalid_type(): void
    {
        $create = $this->postJson('/api/trial', [
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $licenseId = $create->json('data.license.id');

        $response = $this->postJson("/api/trial/{$licenseId}/convert", [
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }
}
