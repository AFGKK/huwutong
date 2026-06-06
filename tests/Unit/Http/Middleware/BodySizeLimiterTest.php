<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\BodySizeLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class BodySizeLimiterTest extends TestCase
{
    protected BodySizeLimiter $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new BodySizeLimiter();
    }

    public function test_get_request_is_not_limited(): void
    {
        $request = Request::create('/api/licenses', 'GET');

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_post_within_limit_passes(): void
    {
        $request = Request::create('/api/license/activate', 'POST', [], [], [], [], json_encode(['key' => 'test']));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_post_exceeds_default_limit(): void
    {
        // 构建 2 MB 的 body
        $largeBody = str_repeat('a', 2 * 1024 * 1024 + 1);
        $request = Request::create('/api/upload', 'POST', [], [], [], [], $largeBody);
        $request->headers->set('Content-Length', (string) strlen($largeBody));

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(413, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('PAYLOAD_TOO_LARGE', $content['error']['code'] ?? '');
    }

    public function test_activate_limit_is_10kb(): void
    {
        // 构建 11 KB 的 body
        $largeBody = str_repeat('a', 11 * 1024);
        $request = Request::create('/api/license/activate', 'POST', [], [], [], [], $largeBody);
        $request->headers->set('Content-Length', (string) strlen($largeBody));

        $response = $this->middleware->handle($request, fn () => new Response('OK'), 'activate');

        $this->assertEquals(413, $response->getStatusCode());
    }

    public function test_upload_limit_is_10mb(): void
    {
        // 构建 5 MB 的 body — 在 upload 限制内
        $body = str_repeat('a', 5 * 1024 * 1024);
        $request = Request::create('/api/upload', 'POST', [], [], [], [], $body);

        $response = $this->middleware->handle($request, fn () => new Response('OK'), 'upload');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_content_length_exceeds_triggers_413(): void
    {
        $request = Request::create('/api/test', 'POST', [], [], [], [], 'small body');
        $request->headers->set('Content-Length', '9999999'); // 虚假的长度

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(413, $response->getStatusCode());
    }

    public function test_custom_numeric_limit(): void
    {
        $body = str_repeat('a', 500);
        $request = Request::create('/api/custom', 'POST', [], [], [], [], $body);

        // 自定义 100 字节限制
        $response = $this->middleware->handle($request, fn () => new Response('OK'), '100');

        $this->assertEquals(413, $response->getStatusCode());
    }
}
