<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/_test/ratelimit', function () {
            return response()->json(['success' => true]);
        })->middleware(RateLimitMiddleware::class . ':5,60,ip');
    }

    public function test_passes_within_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/_test/ratelimit');
            $response->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }

    public function test_exceeds_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/_test/ratelimit')->assertStatus(200);
        }

        // 第 6 次请求应被限流
        $response = $this->postJson('/_test/ratelimit');

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_returns_rate_limit_headers(): void
    {
        $response = $this->postJson('/_test/ratelimit');

        $response->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining')
            ->assertHeader('X-RateLimit-Reset');
    }

    public function test_allows_get_requests(): void
    {
        Route::get('/_test/ratelimit-get', function () {
            return response()->json(['success' => true]);
        })->middleware(RateLimitMiddleware::class);

        $response = $this->getJson('/_test/ratelimit-get');
        $response->assertStatus(200);
    }
}
