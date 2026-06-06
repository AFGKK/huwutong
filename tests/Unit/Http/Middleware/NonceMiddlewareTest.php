<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\NonceMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NonceMiddlewareTest extends TestCase
{
    private NonceMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new NonceMiddleware(60);

        // 注册一个测试路由
        Route::post('/_test/nonce', function () {
            return response()->json(['success' => true]);
        })->middleware(NonceMiddleware::class);
    }

    public function test_passes_with_valid_nonce_and_timestamp(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440001',
            'X-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_rejects_missing_nonce(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_NONCE_OR_TIMESTAMP');
    }

    public function test_rejects_missing_timestamp(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440001',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_NONCE_OR_TIMESTAMP');
    }

    public function test_rejects_invalid_timestamp(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440001',
            'X-Timestamp' => 'abc',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_TIMESTAMP');
    }

    public function test_rejects_expired_timestamp(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440001',
            'X-Timestamp' => (string) (time() - 300),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'TIMESTAMP_OUT_OF_WINDOW');
    }

    public function test_rejects_nonce_outside_window(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440001',
            'X-Timestamp' => (string) (time() + 300),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'TIMESTAMP_OUT_OF_WINDOW');
    }

    public function test_rejects_invalid_nonce_format(): void
    {
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => 'not-a-uuid',
            'X-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_NONCE');
    }

    public function test_rejects_reused_nonce(): void
    {
        // 第一次请求
        $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440002',
            'X-Timestamp' => (string) time(),
        ])->assertStatus(200);

        // 重复使用相同的 nonce
        $response = $this->postJson('/_test/nonce', [], [
            'X-Nonce' => '550e8400-e29b-41d4-a716-446655440002',
            'X-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'NONCE_ALREADY_USED');
    }

    public function test_allows_get_requests_without_validation(): void
    {
        Route::get('/_test/nonce-get', function () {
            return response()->json(['success' => true]);
        })->middleware(NonceMiddleware::class);

        // GET 请求不校验 nonce
        $response = $this->getJson('/_test/nonce-get');
        $response->assertStatus(200);
    }
}
