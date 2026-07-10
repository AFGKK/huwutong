<?php

namespace Tests\Feature\Api;

use App\Models\EmailLog;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EmailTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 概览 ───

    public function test_overview_returns_tracking_stats(): void
    {
        $response = $this->getJson('/api/email-tracking/overview', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['funnel', 'daily', 'by_template', 'period']]);
    }

    // ─── 日志列表 ───

    public function test_logs_returns_paginated(): void
    {
        EmailLog::create([
            'tenant_id' => $this->tenant->id,
            'to_email' => 'test@example.com',
            'subject' => '测试邮件',
            'status' => 'sent',
        ]);

        $response = $this->getJson('/api/email-tracking/logs', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data', 'meta']);
    }

    // ─── 模板详情 ───

    public function test_template_detail_returns_stats(): void
    {
        $response = $this->getJson('/api/email-tracking/template/welcome', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['template_code', 'funnel', 'daily']]);
    }

    // ─── 退信统计 ───

    public function test_bounce_stats_returns_data(): void
    {
        $response = $this->getJson('/api/email-tracking/bounces', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['bounce_categories', 'bounce_rate', 'total_bounced']]);
    }

    // ─── 公开端点 ───

    public function test_tracking_pixel_returns_gif(): void
    {
        $response = $this->get('/api/track/pixel');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
    }

    public function test_tracking_pixel_updates_log(): void
    {
        $log = EmailLog::create([
            'tenant_id' => $this->tenant->id,
            'tracking_id' => 'track_123',
            'to_email' => 'test@example.com',
            'subject' => '追踪测试',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->get("/api/track/pixel?id={$log->tracking_id}");

        $response->assertStatus(200);
        $this->assertNotNull($log->fresh()->opened_at);
    }

    public function test_click_redirect_updates_log(): void
    {
        $log = EmailLog::create([
            'tenant_id' => $this->tenant->id,
            'tracking_id' => 'click_123',
            'to_email' => 'test@example.com',
            'subject' => '点击测试',
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $response = $this->get("/api/track/click?id={$log->tracking_id}&url=https://example.com");

        $response->assertStatus(302);
        $this->assertNotNull($log->fresh()->clicked_at);
    }
}
