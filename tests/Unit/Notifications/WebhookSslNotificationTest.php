<?php

namespace Tests\Unit\Notifications;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Notifications\WebhookFailedNotification;
use App\Notifications\SslCertificateAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSslNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_failed_notification_data(): void
    {
        $tenant = Tenant::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => '测试端点',
            'url' => 'https://example.com/hook',
        ]);

        $notification = new WebhookFailedNotification(
            $endpoint, 'license.activated', 'Connection timeout', 3,
        );

        $user = User::factory()->create();
        $arrayData = $notification->toArray($user);

        $this->assertEquals('webhook_failed', $arrayData['type']);
        $this->assertEquals($endpoint->id, $arrayData['endpoint_id']);
        $this->assertEquals('测试端点', $arrayData['endpoint_name']);
        $this->assertEquals(3, $arrayData['attempts']);
        $this->assertEquals('Connection timeout', $arrayData['error']);
    }

    public function test_ssl_alert_notification_data(): void
    {
        $notification = new SslCertificateAlertNotification(
            'example.com', '2026-07-01', 25, 'expiring_soon',
        );

        $user = User::factory()->create();
        $arrayData = $notification->toArray($user);

        $this->assertEquals('ssl_certificate_alert', $arrayData['type']);
        $this->assertEquals('example.com', $arrayData['domain']);
        $this->assertEquals(25, $arrayData['days_left']);
        $this->assertEquals('expiring_soon', $arrayData['action']);
        $this->assertStringContainsString('example.com', $arrayData['message']);
    }

    public function test_ssl_alert_via_channels(): void
    {
        $user = User::factory()->create();

        // expiring_soon — database + mail
        $n1 = new SslCertificateAlertNotification('a.com', '2026-07-01', 25, 'expiring_soon');
        $channels1 = $n1->via($user);
        $this->assertContains('database', $channels1);
        $this->assertContains('mail', $channels1);

        // renewed — only database
        $n2 = new SslCertificateAlertNotification('a.com', '2027-01-01', 180, 'renewed');
        $channels2 = $n2->via($user);
        $this->assertContains('database', $channels2);
        $this->assertNotContains('mail', $channels2);

        // renew_failed — database + mail
        $n3 = new SslCertificateAlertNotification('a.com', '2026-06-10', 4, 'renew_failed');
        $channels3 = $n3->via($user);
        $this->assertContains('database', $channels3);
        $this->assertContains('mail', $channels3);
    }
}
