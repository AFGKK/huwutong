<?php

namespace Tests\Unit\Services;

use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    private IdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IdempotencyService::class);
        Cache::flush();
    }

    public function test_get_returns_null_for_new_key(): void
    {
        $result = $this->service->get(Str::uuid()->toString());
        $this->assertNull($result);
    }

    public function test_save_and_get_returns_cached_result(): void
    {
        $key = Str::uuid()->toString();
        $data = ['status' => 200, 'body' => ['success' => true, 'data' => ['id' => 1]]];

        $this->service->save($key, $data);
        $cached = $this->service->get($key);

        $this->assertIsArray($cached);
        $this->assertEquals(200, $cached['status']);
        $this->assertTrue($cached['body']['success']);
    }

    public function test_generate_key_returns_uuid(): void
    {
        $key = $this->service->generateKey();
        $this->assertTrue(Str::isUuid($key));
    }

    public function test_is_valid_key(): void
    {
        $this->assertTrue($this->service->isValidKey(Str::uuid()->toString()));
        $this->assertFalse($this->service->isValidKey('invalid-key'));
        $this->assertFalse($this->service->isValidKey(''));
        $this->assertFalse($this->service->isValidKey(null));
    }

    public function test_forget_removes_cached_value(): void
    {
        $key = Str::uuid()->toString();
        $this->service->save($key, ['status' => 200, 'body' => []]);

        $this->assertNotNull($this->service->get($key));
        $this->service->forget($key);
        $this->assertNull($this->service->get($key));
    }
}
