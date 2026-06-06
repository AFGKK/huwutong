<?php

namespace Tests\Unit\Services;

use App\Enums\LicenseStatus;
use App\Events\LicenseAboutToExpire;
use App\Events\LicenseStatusChanged;
use App\Models\License;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\EventBus;
use Database\Factories\LicenseFactory;
use Database\Factories\ProductFactory;
use Database\Factories\TenantFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventBusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private License $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_status_changed_creates_audit_log(): void
    {
        $event = new LicenseStatusChanged(
            $this->license,
            LicenseStatus::Active->value,
            LicenseStatus::Expired->value,
            '测试过期',
        );

        app(EventBus::class)->dispatch($event);

        $this->assertDatabaseHas('logs', [
            'tenant_id' => $this->license->tenant_id,
            'action' => 'license.status_changed',
        ]);
    }

    public function test_status_changed_creates_notification(): void
    {
        $event = new LicenseStatusChanged(
            $this->license,
            LicenseStatus::Active->value,
            LicenseStatus::Expired->value,
        );

        app(EventBus::class)->dispatch($event);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->license->tenant_id,
            'type' => 'status_change',
        ]);
    }

    public function test_status_changed_creates_webhook_event_when_endpoint_subscribed(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->tenant->id,
            'name' => '测试 Webhook',
            'url' => 'https://example.com/webhook',
            'secret' => 'test-secret',
            'events' => ['license.expired'],
            'is_active' => true,
        ]);

        $event = new LicenseStatusChanged(
            $this->license,
            LicenseStatus::Active->value,
            LicenseStatus::Expired->value,
        );

        app(EventBus::class)->dispatch($event);

        $this->assertDatabaseHas('webhook_events', [
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'license.expired',
            'status' => 'delivered',
        ]);
    }

    public function test_status_changed_skips_webhook_when_no_endpoint(): void
    {
        $event = new LicenseStatusChanged(
            $this->license,
            LicenseStatus::Active->value,
            LicenseStatus::Expired->value,
        );

        app(EventBus::class)->dispatch($event);

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_about_to_expire_creates_notification(): void
    {
        $event = new LicenseAboutToExpire($this->license, 7, '7_days');

        app(EventBus::class)->dispatch($event);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->license->tenant_id,
            'type' => 'expiry_warning',
        ]);

        $notification = Notification::first();
        $this->assertStringContainsString('7 天后过期', $notification->content);
    }

    public function test_about_to_expire_with_different_days(): void
    {
        $event1 = new LicenseAboutToExpire($this->license, 3, '3_days');
        app(EventBus::class)->dispatch($event1);

        $notification = Notification::first();
        $this->assertStringContainsString('3 天后过期', $notification->content);
    }

    public function test_auto_expire_command_expires_licenses(): void
    {
        $expiredLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('hwt:auto-expire-licenses')
            ->assertSuccessful();

        $this->assertDatabaseHas('licenses', [
            'id' => $expiredLicense->id,
            'status' => LicenseStatus::Expired->value,
        ]);
    }

    public function test_auto_expire_command_dry_run(): void
    {
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('hwt:auto-expire-licenses', ['--dry-run' => true])
            ->assertSuccessful();

        // 验证没有 License 被真正过期
        $this->assertDatabaseMissing('licenses', [
            'status' => LicenseStatus::Expired->value,
        ]);
    }

    public function test_auto_expire_skips_non_expirable_statuses(): void
    {
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Revoked->value, // 终态，不应该被过期
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('hwt:auto-expire-licenses')
            ->assertSuccessful();

        $this->assertDatabaseHas('licenses', [
            'status' => LicenseStatus::Revoked->value,
        ]);
    }

    public function test_send_expiry_reminders_sends_7_days(): void
    {
        // 7 天后过期的 License
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addDays(7),
        ]);

        $this->artisan('hwt:send-expiry-reminders', ['--level' => '7_days'])
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'type' => 'expiry_warning',
        ]);
    }

    public function test_send_expiry_reminders_skips_wrong_day(): void
    {
        // clear any licenses from setup that might interfere
        License::query()->delete();

        // 6 天后过期的 License（不应触发 7 天提醒）
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addDays(6)->startOfDay(),
        ]);

        $this->artisan('hwt:send-expiry-reminders', ['--level' => '7_days'])
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }
}
