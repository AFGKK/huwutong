<?php

namespace Tests\Unit\Services;

use App\Models\DataProcessingAgreement;
use App\Models\DpaSignature;
use App\Models\GdprDataRequest;
use App\Models\User;
use App\Services\GdprComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GdprComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GdprComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GdprComplianceService();
        Storage::fake('local');
    }

    /** @test */
    public function it_submits_a_dsr_request()
    {
        $user = User::factory()->create();

        $request = $this->service->submitRequest($user->id, GdprDataRequest::TYPE_ACCESS, 'I want to see my data');

        $this->assertDatabaseHas('gdpr_data_requests', [
            'id' => $request->id,
            'user_id' => $user->id,
            'type' => 'access',
            'status' => 'pending',
            'reason' => 'I want to see my data',
        ]);
    }

    /** @test */
    public function it_processes_an_access_request()
    {
        $user = User::factory()->create();
        $request = $this->service->submitRequest($user->id, GdprDataRequest::TYPE_ACCESS);

        $result = $this->service->processAccessRequest($request);

        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->output_file);
        $this->assertNotNull($result->completed_at);
        $this->assertNotNull($result->expires_at);

        // Verify the file was created
        Storage::disk('local')->assertExists($result->output_file);
    }

    /** @test */
    public function it_processes_a_portability_request()
    {
        $user = User::factory()->create();
        $request = $this->service->submitRequest($user->id, GdprDataRequest::TYPE_PORTABILITY);

        $result = $this->service->processPortabilityRequest($request);

        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->output_file);

        // Verify file contents
        $content = json_decode(Storage::disk('local')->get($result->output_file), true);
        $this->assertArrayHasKey('user', $content);
        $this->assertArrayHasKey('format', $content);
        $this->assertEquals('https://schema.org/Person', $content['format']);
    }

    /** @test */
    public function it_processes_an_erasure_request()
    {
        $user = User::factory()->create(['name' => '张三', 'email' => 'test@example.com', 'phone' => '13800138000', 'status' => 'active']);
        $request = $this->service->submitRequest($user->id, GdprDataRequest::TYPE_ERASURE);

        $result = $this->service->processErasureRequest($request);

        $this->assertEquals('completed', $result->status, 'Status should be completed, got: ' . $result->status . ' with notes: ' . ($result->admin_notes ?? 'none'));

        // User data should be anonymized
        $user->refresh();
        $this->assertStringStartsWith('User_anon_', $user->name);
        $this->assertStringContainsString('@anonymized.local', $user->email);
        $this->assertNull($user->phone);
    }

    /** @test */
    public function it_processes_a_rectification_request()
    {
        $user = User::factory()->create(['name' => 'Wrong Name']);
        $request = $this->service->submitRequest($user->id, GdprDataRequest::TYPE_RECTIFICATION);

        $result = $this->service->processRectificationRequest($request, [
            'name' => 'Correct Name',
            'email' => 'correct@example.com',
        ]);

        $this->assertEquals('completed', $result->status);

        $user->refresh();
        $this->assertEquals('Correct Name', $user->name);
        $this->assertEquals('correct@example.com', $user->email);
    }

    /** @test */
    public function it_handles_errors_gracefully()
    {
        $user = User::factory()->create();
        $request = GdprDataRequest::factory()->create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_ACCESS,
            'status' => GdprDataRequest::STATUS_PENDING,
        ]);

        // Delete the user - triggers cascade delete of the request
        $user->delete();

        // The request should have been cascade deleted
        $this->assertNull(GdprDataRequest::find($request->id));
    }

    /** @test */
    public function it_publishes_and_archives_old_dpa()
    {
        $tenant = \App\Models\Tenant::factory()->create();

        $dpa1 = DataProcessingAgreement::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => DataProcessingAgreement::STATUS_PUBLISHED,
        ]);
        $dpa2 = DataProcessingAgreement::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => DataProcessingAgreement::STATUS_DRAFT,
        ]);

        $result = $this->service->publishDpa($dpa2->id);

        $this->assertEquals(DataProcessingAgreement::STATUS_PUBLISHED, $result->status);
        $this->assertNotNull($result->effective_at);

        // Old DPA should be archived
        $dpa1->refresh();
        $this->assertEquals(DataProcessingAgreement::STATUS_ARCHIVED, $dpa1->status);
    }

    /** @test */
    public function it_signs_a_dpa()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $dpa = DataProcessingAgreement::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => DataProcessingAgreement::STATUS_PUBLISHED,
        ]);

        $signature = $this->service->signDpa($dpa->id, $user->tenant_id, $user->id);

        $this->assertInstanceOf(DpaSignature::class, $signature);
        $this->assertEquals($user->id, $signature->signed_by);
        $this->assertEquals($user->name, $signature->signer_name);
    }

    /** @test */
    public function it_prevents_double_dpa_signature()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $dpa = DataProcessingAgreement::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => DataProcessingAgreement::STATUS_PUBLISHED,
        ]);

        $this->service->signDpa($dpa->id, $user->tenant_id, $user->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('已签署此 DPA');

        $this->service->signDpa($dpa->id, $user->tenant_id, $user->id);
    }

    /** @test */
    public function it_prevents_signing_draft_dpa()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $dpa = DataProcessingAgreement::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => DataProcessingAgreement::STATUS_DRAFT,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DPA 尚未发布');

        $this->service->signDpa($dpa->id, $user->tenant_id, $user->id);
    }

    /** @test */
    public function it_returns_gdpr_stats()
    {
        $user = User::factory()->create();

        GdprDataRequest::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'pending']);
        GdprDataRequest::factory()->count(5)->create(['user_id' => $user->id, 'status' => 'completed']);
        GdprDataRequest::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'failed']);

        $tenant = \App\Models\Tenant::factory()->create();
        DataProcessingAgreement::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'status' => DataProcessingAgreement::STATUS_PUBLISHED,
        ]);

        $stats = $this->service->getStats();

        $this->assertEquals(9, $stats['total_requests']);
        $this->assertEquals(3, $stats['pending']);
        $this->assertEquals(5, $stats['completed']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(2, $stats['published_dpa']);
    }

    /** @test */
    public function it_collects_personal_data_structure()
    {
        $user = User::factory()->create();

        $data = $this->service->collectPersonalData($user);

        $this->assertArrayHasKey('user_profile', $data);
        $this->assertArrayHasKey('licenses', $data);
        $this->assertArrayHasKey('subscriptions', $data);
        $this->assertArrayHasKey('invoices', $data);
        $this->assertArrayHasKey('data_controller', $data);
        $this->assertEquals($user->email, $data['user_profile']['email']);
    }

    /** @test */
    public function it_collects_portable_data_structure()
    {
        $user = User::factory()->create();

        $data = $this->service->collectPortableData($user);

        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('format', $data);
        $this->assertArrayHasKey('licenses', $data);
        $this->assertArrayHasKey('publisher', $data);
    }
}
