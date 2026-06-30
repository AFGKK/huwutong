<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\CrossBorderTransfer;
use App\Models\Dpia;
use App\Models\PersonalDataInventory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiplComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        ApiVersion::create([
            'version' => 'v1',
            'base_path' => '/api/v1',
            'name' => 'v1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('admin-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_inventory_lists_items(): void
    {
        PersonalDataInventory::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/pipl/inventory', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(3, $response->json('data.total'));
    }

    public function test_inventory_update(): void
    {
        $item = PersonalDataInventory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'field_name' => 'email',
            'classification' => 'L2',
        ]);

        $response = $this->putJson(
            "/api/pipl/inventory/{$item->id}",
            ['classification' => 'L3', 'purpose' => '身份验证'],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.classification', 'L3');
    }

    public function test_create_cross_border_transfer(): void
    {
        $response = $this->postJson('/api/pipl/cross-border-transfers', [
            'data_category' => '用户账户信息',
            'recipient_country' => '美国',
            'recipient_name' => 'AWS Inc.',
            'recipient_purpose' => '云服务器托管',
            'transfer_method' => 'cloud',
            'legal_basis' => 'standard_clauses',
            'security_measures' => 'TLS 1.3 + AES-256',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipient_country', '美国');

        $this->assertDatabaseHas('cross_border_transfers', [
            'tenant_id' => $this->tenant->id,
            'recipient_country' => '美国',
        ]);
    }

    public function test_review_cross_border_transfer(): void
    {
        $transfer = CrossBorderTransfer::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => '用户信息',
            'recipient_country' => '新加坡',
            'recipient_name' => 'AliCloud SG',
            'recipient_purpose' => 'CDN 加速',
            'transfer_method' => 'api',
            'legal_basis' => 'consent',
            'status' => 'active',
        ]);

        $response = $this->postJson(
            "/api/pipl/cross-border-transfers/{$transfer->id}/review",
            ['impact_assessment' => '风险可控，建议年度复评'],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.impact_assessment', '风险可控，建议年度复评');
    }

    public function test_dpia_create_and_complete(): void
    {
        $create = $this->postJson('/api/pipl/dpias', [
            'title' => 'License 授权数据处理影响评估',
            'description' => '评估授权流程中的个人信息处理',
            'involved_data_categories' => ['用户账户信息', '设备指纹'],
            'stakeholders' => ['数据保护官', '产品经理'],
        ], $this->authHeaders());

        $create->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $dpiaId = $create->json('data.id');

        $this->postJson("/api/pipl/dpias/{$dpiaId}/complete", [
            'necessity_assessment' => '授权验证需要处理设备信息',
            'risk_assessment' => '中等风险',
            'mitigation_measures' => '数据脱敏展示',
            'conclusion' => '通过评估',
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_stats_returns_overview(): void
    {
        PersonalDataInventory::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        CrossBorderTransfer::create([
            'tenant_id' => $this->tenant->id,
            'data_category' => '测试',
            'recipient_country' => '日本',
            'recipient_name' => 'Test Co.',
            'recipient_purpose' => '测试',
            'transfer_method' => 'api',
            'legal_basis' => 'consent',
        ]);
        Dpia::factory()->create(['created_by' => $this->user->id, 'status' => 'completed']);

        $response = $this->getJson('/api/pipl/stats', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cross_border.total', 1)
            ->assertJsonPath('data.dpia.completed', 1);
    }

    public function test_sensitive_fields_returns_definitions(): void
    {
        $response = $this->getJson('/api/pipl/sensitive-fields', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertArrayHasKey('password', $response->json('data'));
        $this->assertEquals('L4', $response->json('data.password.level'));
    }

    public function test_inventory_batch_update(): void
    {
        $items = collect([
            PersonalDataInventory::factory()->create([
                'tenant_id' => $this->tenant->id,
                'table_name' => 'users',
                'field_name' => 'email',
                'status' => 'active',
            ]),
            PersonalDataInventory::factory()->create([
                'tenant_id' => $this->tenant->id,
                'table_name' => 'users',
                'field_name' => 'phone',
                'status' => 'active',
            ]),
        ]);

        $response = $this->postJson('/api/pipl/inventory/batch-update', [
            'ids' => $items->pluck('id')->all(),
            'data' => ['status' => 'archived'],
        ], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(2, PersonalDataInventory::where('status', 'archived')->count());
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/pipl/stats')->assertStatus(401);
    }
}
