<?php

namespace Tests\Feature;

use App\Models\PricingExperiment;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingExperimentApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Tenant::find(1)) {
            Tenant::factory()->create(['id' => 1]);
        }

        $this->user = User::factory()->create(['tenant_id' => 1]);
        Sanctum::actingAs($this->user, ['*']);
    }

    /** @test */
    public function can_list_experiments()
    {
        PricingExperiment::create(['name' => 'A', 'slug' => 'exp-a-' . Str::random(4), 'status' => 'draft', 'traffic_split' => 50, 'created_by' => $this->user->id]);
        PricingExperiment::create(['name' => 'B', 'slug' => 'exp-b-' . Str::random(4), 'status' => 'running', 'traffic_split' => 30, 'starts_at' => now(), 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/admin/pricing/dynamic/experiments');

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function can_create_experiment()
    {
        $response = $this->postJson('/api/admin/pricing/dynamic/experiments', [
            'name' => 'Test Create Exp',
            'traffic_split' => 50,
            'treatment_config' => ['adjustment_type' => 'percentage', 'adjustment_value' => -10],
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function can_start_experiment()
    {
        $exp = PricingExperiment::create([
            'name' => 'Startable',
            'slug' => 'startable-' . Str::random(4),
            'status' => 'draft',
            'traffic_split' => 50,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/admin/pricing/dynamic/experiments/{$exp->id}/start");

        $response->assertOk();
        $this->assertEquals('running', $exp->fresh()->status);
    }

    /** @test */
    public function can_complete_experiment()
    {
        $exp = PricingExperiment::create([
            'name' => 'Completable',
            'slug' => 'completable-' . Str::random(4),
            'status' => 'running',
            'traffic_split' => 50,
            'starts_at' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/admin/pricing/dynamic/experiments/{$exp->id}/complete");

        $response->assertOk();
        $exp->refresh();
        $this->assertEquals('completed', $exp->status);
        $this->assertNotNull($exp->results);
    }

    /** @test */
    public function can_get_experiment_stats()
    {
        PricingExperiment::create(['tenant_id' => 1, 'name' => 'S1', 'slug' => 's1-' . Str::random(4), 'status' => 'running', 'traffic_split' => 50, 'starts_at' => now(), 'created_by' => $this->user->id]);
        PricingExperiment::create(['tenant_id' => 1, 'name' => 'S2', 'slug' => 's2-' . Str::random(4), 'status' => 'draft', 'traffic_split' => 50, 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/admin/pricing/dynamic/experiment-stats');

        $response->assertOk();
        $response->assertJsonPath('data.total', 2);
        $response->assertJsonPath('data.running', 1);
        $response->assertJsonPath('data.draft', 1);
    }

    // ═══════════════ M3-26 API 测试 ═══════════════

    /** @test */
    public function can_apply_winning_treatment()
    {
        $exp = PricingExperiment::create([
            'name' => 'Winner',
            'slug' => 'winner-' . Str::random(4),
            'status' => 'completed',
            'traffic_split' => 50,
            'treatment_config' => ['adjustment_type' => 'percentage', 'adjustment_value' => -15],
            'results' => [
                'control' => ['count' => 100, 'converted' => 10, 'conversion_rate' => 10.00, 'avg_revenue' => 100.00, 'churned' => 5, 'churn_rate' => 5.00],
                'treatment' => ['count' => 100, 'converted' => 20, 'conversion_rate' => 20.00, 'avg_revenue' => 85.00, 'churned' => 3, 'churn_rate' => 3.00],
                'improvement' => ['conversion_rate' => 10.00, 'avg_revenue' => -15.00, 'churn_rate' => -2.00],
                'significance' => ['z_score' => 2.5, 'p_value' => 0.0124, 'significant' => true],
                'calculated_at' => now()->toIso8601String(),
            ],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/admin/pricing/dynamic/experiments/{$exp->id}/apply-winning");

        $response->assertOk();
        $response->assertJsonPath('data.is_significant', true);
        $response->assertJsonPath('data.experiment_id', $exp->id);
    }

    /** @test */
    public function can_get_recommendations()
    {
        PricingExperiment::create([
            'tenant_id' => 1,
            'name' => 'Rec1',
            'slug' => 'rec1-' . Str::random(4),
            'status' => 'completed',
            'traffic_split' => 50,
            'results' => [
                'control' => ['count' => 50, 'converted' => 5, 'conversion_rate' => 10.00, 'avg_revenue' => 100.00, 'churned' => 2, 'churn_rate' => 4.00],
                'treatment' => ['count' => 50, 'converted' => 8, 'conversion_rate' => 16.00, 'avg_revenue' => 95.00, 'churned' => 1, 'churn_rate' => 2.00],
                'improvement' => ['conversion_rate' => 6.00, 'avg_revenue' => -5.00, 'churn_rate' => -2.00],
                'significance' => ['z_score' => 1.8, 'p_value' => 0.0718, 'significant' => false],
                'calculated_at' => now()->toIso8601String(),
            ],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/admin/pricing/dynamic/recommendations');

        $response->assertOk();
        $response->assertJsonPath('data.total_analyzed', 1);
    }

    /** @test */
    public function can_batch_assign_customer_to_experiment()
    {
        $exp = PricingExperiment::create([
            'name' => 'BatchAssign',
            'slug' => 'batch-' . Str::random(4),
            'status' => 'running',
            'traffic_split' => 50,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'created_by' => $this->user->id,
        ]);

        $customer = \App\Models\Customer::factory()->create(['tenant_id' => 1]);

        $response = $this->postJson('/api/admin/pricing/dynamic/batch-assign', [
            'customer_id' => $customer->id,
            'current_price' => 100.00,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assigned_count', 1);
    }
}
