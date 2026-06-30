<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\MonthlyRevenueSnapshot;
use App\Models\RevenueRecognitionLine;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RevenueRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueRecognitionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RevenueRecognitionService $service;
    protected Tenant $tenant;
    protected Subscription $subscription;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(RevenueRecognitionService::class);
        $this->tenant = Tenant::factory()->create();

        $this->subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 1200,
            'currency' => 'CNY',
            'billing_period' => 'yearly',
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->addMonths(10),
        ]);

        $this->invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'amount' => 1200,
            'subtotal' => 1200,
            'currency' => 'CNY',
            'status' => 'paid',
            'paid_at' => now()->subMonths(2),
            'billing_reason' => 'subscription_create',
        ]);
    }

    public function test_it_creates_yearly_schedule_with_12_periods(): void
    {
        $schedule = $this->service->createSchedule($this->invoice);

        $this->assertNotNull($schedule);
        $this->assertEquals(1200, (float) $schedule->total_amount);
        $this->assertEquals(12, $schedule->total_periods);
        $this->assertEquals('active', $schedule->status);
        $this->assertEquals(0, $schedule->recognized_periods);
        $this->assertEquals(1200, (float) $schedule->deferred_amount);

        // 检查是否创建了12条明细行
        $this->assertEquals(12, RevenueRecognitionLine::where('schedule_id', $schedule->id)->count());
    }

    public function test_it_creates_monthly_schedule_with_1_period(): void
    {
        $monthlySub = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 100,
            'currency' => 'CNY',
            'billing_period' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $monthlyInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $monthlySub->id,
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
            'billing_reason' => 'subscription_create',
        ]);

        $schedule = $this->service->createSchedule($monthlyInvoice);

        $this->assertEquals(1, $schedule->total_periods);
        $this->assertEquals('completed', $schedule->status);
        $this->assertEquals(100, (float) $schedule->recognized_amount);
        $this->assertEquals(0, (float) $schedule->deferred_amount);
    }

    public function test_it_creates_quarterly_schedule_with_3_periods(): void
    {
        $quarterlySub = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 300,
            'currency' => 'CNY',
            'billing_period' => 'quarterly',
        ]);

        $quarterlyInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $quarterlySub->id,
            'amount' => 300,
            'status' => 'paid',
            'paid_at' => now()->subMonth(),
            'billing_reason' => 'subscription_create',
        ]);

        $schedule = $this->service->createSchedule($quarterlyInvoice);

        $this->assertEquals(3, $schedule->total_periods);
        $this->assertEquals(3, RevenueRecognitionLine::where('schedule_id', $schedule->id)->count());
    }

    public function test_it_processes_recognition(): void
    {
        $schedule = $this->service->createSchedule($this->invoice);

        // 年付排程从 paid_at（两个月前）开始，前3期已经到期
        $result = $this->service->processRecognition();

        // 应该有3期待确认（paid_at 是两个月前，第1、2、3期都应已到期）
        $this->assertEquals(3, $result['recognized_count']);

        $schedule->refresh();
        $this->assertEquals(3, $schedule->recognized_periods);
        $this->assertEquals(300, (float) $schedule->recognized_amount); // 100 * 3
        $this->assertEquals(900, (float) $schedule->deferred_amount);
    }

    public function test_it_calculates_deferred_revenue(): void
    {
        $schedule = $this->service->createSchedule($this->invoice);

        $deferred = $this->service->getDeferredRevenue(null, $this->tenant->id);

        $this->assertEquals(1200, $deferred);
    }

    public function test_it_gets_summary(): void
    {
        $this->service->createSchedule($this->invoice);

        $summary = $this->service->getSummary($this->tenant->id);

        $this->assertEquals(1200, $summary['total_amount']);
        $this->assertEquals(0, $summary['recognized_amount']);
        $this->assertEquals(1200, $summary['deferred_amount']);
        $this->assertEquals(0, $summary['completed_schedules']);
    }

    public function test_it_generates_monthly_snapshot(): void
    {
        $snapshot = $this->service->generateMonthlySnapshot($this->tenant->id, now()->format('Y-m'));

        $this->assertNotNull($snapshot);
        $this->assertEquals($this->tenant->id, $snapshot->tenant_id);
        $this->assertEquals(now()->format('Y-m'), $snapshot->year_month);

        // Active subscription count
        $this->assertGreaterThanOrEqual(1, $snapshot->active_subscriptions);
    }

    public function test_it_generates_asc606_report(): void
    {
        $this->service->createSchedule($this->invoice);

        $report = $this->service->generateASC606Report(
            $this->tenant->id,
            now()->format('Y'),
            now()->format('m'),
        );

        $this->assertArrayHasKey('report_period', $report);
        $this->assertArrayHasKey('opening_deferred_revenue', $report);
        $this->assertArrayHasKey('closing_deferred_revenue', $report);
        $this->assertArrayHasKey('total_invoiced', $report);
        $this->assertArrayHasKey('recognized_revenue', $report);

        // 排程创建时，确认了第1期（月付）或还未确认
        $this->assertGreaterThanOrEqual(0, $report['closing_deferred_revenue']);
    }

    public function test_it_creates_schedules_for_existing_invoices(): void
    {
        $result = $this->service->createSchedulesForExistingInvoices($this->tenant->id);

        $this->assertGreaterThanOrEqual(1, $result['total_candidates']);
        $this->assertGreaterThanOrEqual(1, $result['created']);
    }

    public function test_get_period_count(): void
    {
        $this->assertEquals(1, $this->service->getPeriodCount('monthly'));
        $this->assertEquals(3, $this->service->getPeriodCount('quarterly'));
        $this->assertEquals(6, $this->service->getPeriodCount('semi_annually'));
        $this->assertEquals(12, $this->service->getPeriodCount('yearly'));
    }

    // ═══════════════ M3-55 增强测试 ═══════════════

    /** @test */
    public function it_cancels_an_active_schedule(): void
    {
        $schedule = $this->service->createSchedule($this->invoice);

        // 确认一些行
        $this->service->processRecognition(now()->addMonths(3));

        $cancelled = $this->service->cancelSchedule($schedule->id, 'test_refund');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals(0, (float) $cancelled->deferred_amount);
        $this->assertEquals('test_refund', $cancelled->cancel_reason);
        $this->assertNotNull($cancelled->cancelled_at);

        // 所有待确认行应为 skipped
        $pendingCount = RevenueRecognitionLine::where('schedule_id', $schedule->id)
            ->where('status', 'pending')
            ->count();
        $this->assertEquals(0, $pendingCount);

        $skippedCount = RevenueRecognitionLine::where('schedule_id', $schedule->id)
            ->where('status', 'skipped')
            ->count();
        $this->assertGreaterThan(0, $skippedCount);
    }

    /** @test */
    public function it_recomputes_a_schedule(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'amount' => 2400,
            'currency' => 'CNY',
            'status' => 'paid',
            'paid_at' => now()->subMonths(2),
            'billing_reason' => 'subscription_create',
        ]);

        $schedule = $this->service->createSchedule($invoice);

        // 确认几期
        $this->service->processRecognition(now()->addMonths(1));
        $this->assertEquals(200, (float) $schedule->fresh()->recognized_amount); // 2 periods * ~100

        // 重算
        $recomputed = $this->service->recomputeSchedule($schedule->id);

        $this->assertEquals('active', $recomputed->status);
    }

    /** @test */
    public function it_exports_asc606_csv(): void
    {
        $this->service->createSchedule($this->invoice);

        $csv = $this->service->exportASC606Csv(
            $this->tenant->id,
            now()->format('Y'),
            now()->format('m'),
        );

        $this->assertStringContainsString('期间,类型,描述,金额,币种', $csv);
        $this->assertStringContainsString('SUMMARY', $csv);
        $this->assertStringContainsString('TRANSACTION', $csv);
    }

    /** @test */
    public function it_rejects_cancelling_already_cancelled_schedule(): void
    {
        $this->expectException(\RuntimeException::class);

        $schedule = $this->service->createSchedule($this->invoice);
        $this->service->cancelSchedule($schedule->id, 'first_cancel');
        $this->service->cancelSchedule($schedule->id, 'double_cancel');
    }

    /** @test */
    public function asc606_report_includes_product_breakdown(): void
    {
        $this->service->createSchedule($this->invoice);

        $report = $this->service->generateASC606Report(
            $this->tenant->id,
            now()->format('Y'),
            now()->format('m'),
        );

        $this->assertArrayHasKey('product_breakdown', $report);
        $this->assertArrayHasKey('recognition_method', $report);
    }
}
