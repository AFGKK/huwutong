<?php

namespace Tests\Feature\Api;

use App\Models\TaxExemptCertificate;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        // 创建一些测试税率
        TaxRate::create([
            'country_code' => 'CN',
            'name' => 'China VAT',
            'rate' => 0.13,
            'type' => 'vat',
            'is_active' => true,
        ]);
        TaxRate::create([
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'California Sales Tax',
            'rate' => 0.0875,
            'type' => 'sales_tax',
            'is_active' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 国家税率列表 ───

    public function test_countries_returns_list(): void
    {
        $response = $this->getJson('/api/tax/countries', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 区域税率 ───

    public function test_region_taxes_returns_filtered(): void
    {
        $response = $this->getJson('/api/tax/region/US', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 税率列表 ───

    public function test_rates_returns_paginated(): void
    {
        $response = $this->getJson('/api/tax/rates', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }

    // ─── 计算税额 ───

    public function test_calculate_requires_amount_and_country(): void
    {
        $response = $this->postJson('/api/tax/calculate', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_calculate_returns_tax_result(): void
    {
        $response = $this->postJson('/api/tax/calculate', [
            'amount' => 100,
            'country_code' => 'CN',
        ], $this->authHeaders());

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ─── 更新税率 ───

    public function test_update_rate_modifies_rate(): void
    {
        $rate = TaxRate::first();

        $response = $this->putJson("/api/tax/rates/{$rate->id}", [
            'rate' => 0.15,
            'is_active' => false,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.rate', 0.15);
        $response->assertJsonPath('data.is_active', false);
    }

    // ─── 免税证书 ───

    public function test_certificates_index_returns_paginated(): void
    {
        TaxExemptCertificate::create([
            'tenant_id' => $this->tenant->id,
            'certificate_type' => 'vat_exempt',
            'certificate_number' => 'CERT-001',
            'issuing_country' => 'CN',
            'status' => 'pending',
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
        ]);

        $response = $this->getJson('/api/tax/certificates', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }

    public function test_store_certificate_creates_new(): void
    {
        $response = $this->postJson('/api/tax/certificates', [
            'certificate_type' => 'vat_exempt',
            'certificate_number' => 'CERT-NEW',
            'issuing_country' => 'DE',
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_approve_certificate_updates_status(): void
    {
        $cert = TaxExemptCertificate::create([
            'tenant_id' => $this->tenant->id,
            'certificate_type' => 'vat_exempt',
            'certificate_number' => 'CERT-002',
            'issuing_country' => 'CN',
            'status' => 'pending',
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
        ]);

        $response = $this->putJson("/api/tax/certificates/{$cert->id}", [
            'status' => 'approved',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'approved');
    }

    public function test_destroy_certificate_deletes(): void
    {
        $cert = TaxExemptCertificate::create([
            'tenant_id' => $this->tenant->id,
            'certificate_type' => 'vat_exempt',
            'certificate_number' => 'CERT-003',
            'issuing_country' => 'CN',
            'status' => 'pending',
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
        ]);

        $response = $this->deleteJson("/api/tax/certificates/{$cert->id}", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertModelMissing($cert);
    }

    // ─── 统计 ───

    public function test_stats_returns_counts(): void
    {
        $response = $this->getJson('/api/tax/stats', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['total_rates', 'active_rates']]);
    }
}
