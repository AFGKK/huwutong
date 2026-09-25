<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 整体测试报告 v5 回归
 */
class SecurityV5RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_does_not_enumerate_users(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'exists_v5@test.local',
            'status' => 'active',
        ]);

        $missing = $this->postJson('/api/forgot-password', [
            'email' => 'missing_v5_' . uniqid() . '@test.local',
        ]);
        $exists = $this->postJson('/api/forgot-password', [
            'email' => 'exists_v5@test.local',
        ]);

        $missing->assertOk();
        $exists->assertOk();

        $this->assertSame(
            $missing->json('message'),
            $exists->json('message')
        );
        $this->assertStringNotContainsString(
            'invalid',
            strtolower((string) $missing->getContent())
        );
        $this->assertStringNotContainsString(
            'selected email',
            strtolower((string) $missing->getContent())
        );
    }

    public function test_register_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'name' => 'Flood'.$i,
                'email' => "flood_v5_{$i}_" . uniqid() . '@test.local',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])->assertSuccessful();
        }

        $blocked = $this->postJson('/api/register', [
            'name' => 'FloodOver',
            'email' => 'flood_v5_over_' . uniqid() . '@test.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $blocked->assertStatus(429);
    }

    public function test_sourcemap_path_is_forbidden_via_middleware(): void
    {
        $response = $this->get('/build/assets/admin-fake.js.map');
        $response->assertForbidden();
    }
}
