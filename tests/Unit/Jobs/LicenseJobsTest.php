<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AutoExpireBulkJob;
use App\Jobs\ExpireLicenseJob;
use App\Models\License;
use App\Models\Tenant;
use App\Services\LicenseService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LicenseJobsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_auto_expire_bulk_dispatches_expire_jobs(): void
    {
        Bus::fake();

        License::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        // 不过期的 License
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $job = new AutoExpireBulkJob();
        $job->handle(app(LicenseService::class));

        Bus::assertDispatched(ExpireLicenseJob::class, 3);
    }

    public function test_auto_expire_bulk_skips_already_expired(): void
    {
        Bus::fake();

        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $job = new AutoExpireBulkJob();
        $job->handle(app(LicenseService::class));

        Bus::assertNotDispatched(ExpireLicenseJob::class);
    }

    public function test_expire_license_job_expires_active_license(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $job = new ExpireLicenseJob($license->id);
        $job->handle(app(LicenseService::class));

        $license->refresh();
        $this->assertEquals('expired', $license->status);
    }

    public function test_expire_license_job_skips_already_expired(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'expired',
        ]);

        $job = new ExpireLicenseJob($license->id);
        $job->handle(app(LicenseService::class));

        $license->refresh();
        $this->assertEquals('expired', $license->status);
    }

    public function test_send_expiry_reminder_command_finds_correct_licenses(): void
    {
        Queue::fake();

        $expiresAt = now()->addDays(7);

        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ]);

        // 不应该匹配的 License
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => now()->addDays(3),
        ]);

        $this->artisan('hwt:send-expiry-reminders', ['--level' => '7_days'])
            ->assertSuccessful();

        Queue::assertPushed(\App\Jobs\SendLicenseExpiryReminderJob::class, 1);
    }
}
