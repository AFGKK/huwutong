<?php

namespace Tests\Unit\Models;

use App\Models\ApiKey;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyPermissionTest extends TestCase
{
    #[Test]
    public function read_only_cannot_write(): void
    {
        $key = new ApiKey(['permissions' => 'read-only']);
        $this->assertTrue($key->canMethod('GET'));
        $this->assertTrue($key->canMethod('HEAD'));
        $this->assertTrue($key->canMethod('OPTIONS'));
        $this->assertFalse($key->canMethod('POST'));
        $this->assertFalse($key->canMethod('PUT'));
        $this->assertFalse($key->canMethod('PATCH'));
        $this->assertFalse($key->canMethod('DELETE'));
    }

    #[Test]
    public function read_write_can_all_methods(): void
    {
        $key = new ApiKey(['permissions' => 'read-write']);
        $this->assertTrue($key->canMethod('GET'));
        $this->assertTrue($key->canMethod('POST'));
        $this->assertTrue($key->canMethod('PUT'));
        $this->assertTrue($key->canMethod('DELETE'));
    }

    #[Test]
    public function admin_can_all_methods(): void
    {
        $key = new ApiKey(['permissions' => 'admin']);
        $this->assertTrue($key->canMethod('GET'));
        $this->assertTrue($key->canMethod('POST'));
        $this->assertTrue($key->canMethod('DELETE'));
    }

    #[Test]
    public function allowed_endpoints_restricts_access(): void
    {
        $key = new ApiKey([
            'permissions' => 'read-write',
            'allowed_endpoints' => ['api/license/*', 'api/licenses/*'],
        ]);

        $this->assertTrue($key->canAccess('api/license/activate'));
        $this->assertTrue($key->canAccess('api/licenses/123'));
        $this->assertTrue($key->canAccess('api/licenses/456/edit'));
        $this->assertFalse($key->canAccess('api/customers'));
        $this->assertFalse($key->canAccess('api/devices'));
    }

    #[Test]
    public function null_allowed_endpoints_allows_all(): void
    {
        $key = new ApiKey(['permissions' => 'read-write', 'allowed_endpoints' => null]);

        $this->assertTrue($key->canAccess('api/license/activate'));
        $this->assertTrue($key->canAccess('api/customers'));
    }

    #[Test]
    public function empty_allowed_endpoints_allows_all(): void
    {
        $key = new ApiKey(['permissions' => 'read-write', 'allowed_endpoints' => []]);

        $this->assertTrue($key->canAccess('api/license/activate'));
    }

    #[Test]
    public function has_quota_when_null(): void
    {
        $key = new ApiKey(['usage_quota' => null, 'usage_count' => 0]);
        $this->assertTrue($key->hasQuota());
    }

    #[Test]
    public function has_quota_when_under_limit(): void
    {
        $key = new ApiKey(['usage_quota' => 100, 'usage_count' => 50]);
        $this->assertTrue($key->hasQuota());
    }

    #[Test]
    public function has_quota_when_at_limit(): void
    {
        $key = new ApiKey(['usage_quota' => 100, 'usage_count' => 100]);
        $this->assertFalse($key->hasQuota());
    }

    #[Test]
    public function matches_ip_when_null(): void
    {
        $key = new ApiKey(['allowed_ip' => null]);
        $this->assertTrue($key->matchesIp('192.168.1.1'));
    }

    #[Test]
    public function matches_ip_when_specified(): void
    {
        $key = new ApiKey(['allowed_ip' => '192.168.1.100']);
        $this->assertTrue($key->matchesIp('192.168.1.100'));
        $this->assertFalse($key->matchesIp('192.168.1.1'));
    }
}
