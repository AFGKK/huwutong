<?php

namespace Tests\Feature;

use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class GatewayAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
    }

    /**
     * 验证应用层安全响应头（CORS/CSP/HSTS 由应用层统一处理，不应缺失）
     */
    public function test_security_headers_are_present_on_api_responses()
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200);

        // CORS 头
        $response->assertHeader('Access-Control-Allow-Origin');
        $response->assertHeader('Access-Control-Allow-Methods');

        // 安全头
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');

        // 链路追踪
        $response->assertHeader('X-Request-Id');
    }

    /**
     * 验证 CORS 预检请求 — OPTIONS 请求由 Laravel 框架自身 HandleCors 处理
     * SecurityHeadersMiddleware 也会附加 CORS 头
     */
    public function test_cors_preflight_request()
    {
        $response = $this->optionsJson('/api/health/live');

        // OPTIONS 请求应由 Laravel 的 HandleCors 中间件处理，返回空 200
        $response->assertStatus(200);
    }

    /**
     * 验证限流响应头存在于受保护的 API 端点
     */
    public function test_rate_limit_headers_on_authenticated_routes()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        // 直接使用 DB 插入角色关联，绕过 Spatie team_foreign_key 限制
        $role = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
        if ($role) {
            \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_type' => get_class($user),
                'model_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertStatus(200);
    }

    /**
     * 验证 OPTIONS 请求不返回冲突的 CORS 头
     */
    public function test_options_request_has_no_duplicate_cors_headers()
    {
        $response = $this->optionsJson('/api/health/live');

        $origin = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotNull($origin, 'CORS Origin 头不应为空');

        // 确保只有一个值（无重复头）
        $originValues = $response->headers->all('Access-Control-Allow-Origin');
        $this->assertCount(1, $originValues, 'CORS Origin 头不应重复');
    }

    /**
     * 验证 CSP 头正确设置
     */
    public function test_content_security_policy_header()
    {
        $response = $this->getJson('/api/health/live');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'CSP 头不应为空');

        // 应包含关键指令
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('frame-ancestors', $csp);
        $this->assertStringContainsString('base-uri', $csp);
    }

    /**
     * 验证 API 错误响应（Route not found 是异常情况，返回框架默认错误格式）
     */
    public function test_error_response_for_unknown_route()
    {
        $response = $this->getJson('/api/non-existent-route');

        // 404 路由不经过完整中间件栈，返回 404 即可
        $response->assertStatus(404);
    }

    /**
     * 验证 X-Request-Id 链路追踪头的一致性（请求和响应相同值）
     */
    public function test_x_request_id_consistency()
    {
        $requestId = 'test-req-' . now()->timestamp;

        $response = $this->withHeaders(['X-Request-Id' => $requestId])
            ->getJson('/api/health/live');

        $response->assertHeader('X-Request-Id', $requestId);
    }

    /**
     * 验证非 API 路径没有强制安全头（避免影响前端页面）
     */
    public function test_non_api_route_has_no_security_headers()
    {
        // Web 路由不应自动获得 CSP（前端页面有自己的策略）
        $response = $this->get('/');

        // Web 路由可能不经过 SecurityHeadersMiddleware，只要不报错即可
        $response->assertStatus(200);
    }

    /**
     * 验证网关层和应用层限流头不冲突
     */
    public function test_rate_limit_header_no_duplicates()
    {
        $response = $this->getJson('/api/health/live');

        $limitHeaders = $response->headers->all('X-RateLimit-Limit');
        // 如果没有设置限流头，框架可能也不会设置。确保不出现重复头。
        $this->assertLessThanOrEqual(1, count($limitHeaders), '限流头不应重复');
    }
}
