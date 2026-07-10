<?php

namespace Tests\Unit\Notifications;

use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LicenseExpiryNotification;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LicenseExpiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_contains_correct_data(): void
    {
        $tenant = Tenant::factory()->create();
        $license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'expires_at' => now()->addDays(7),
        ]);

        $notification = new LicenseExpiryNotification($license, '7_days');

        $arrayData = $notification->toArray($user = new User());

        $this->assertEquals('license_expiry', $arrayData['type']);
        $this->assertEquals('7_days', $arrayData['level']);
        $this->assertEquals($license->id, $arrayData['license_id']);
        $this->assertEquals($license->license_key, $arrayData['license_key']);
        $this->assertStringContainsString($license->license_key, $arrayData['message']);
    }

    public function test_notification_via_channels_by_level(): void
    {
        $license = License::factory()->create();
        $user = User::factory()->create();

        // 7_days — 只有 database
        $n1 = new LicenseExpiryNotification($license, '7_days');
        $channels1 = $n1->via($user);
        $this->assertContains('database', $channels1);
        $this->assertNotContains('mail', $channels1);

        // 1_day — database + mail
        $n2 = new LicenseExpiryNotification($license, '1_day');
        $channels2 = $n2->via($user);
        $this->assertContains('database', $channels2);
        $this->assertContains('mail', $channels2);

        // expired — database + mail
        $n3 = new LicenseExpiryNotification($license, 'expired');
        $channels3 = $n3->via($user);
        $this->assertContains('database', $channels3);
        $this->assertContains('mail', $channels3);
    }
}
