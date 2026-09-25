<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\SignatureMiddleware;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SignatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'metadata' => ['signature_secret' => 'test-secret-key-12345'],
        ]);

        Route::post('/_test/signature', function () {
            return response()->json(['success' => true]);
        })->middleware(SignatureMiddleware::class);
    }

    /**
     * 计算签名（与客户端算法一致）
     */
    private function computeSignature(string $secret, string $method, string $path, string $body, string $timestamp, string $nonce = ''): string
    {
        $canonicalString = implode("\n", [$method, $path, $body, $timestamp, $nonce]);
        return base64_encode(hash_hmac('sha256', $canonicalString, $secret, true));
    }

    public function test_passes_with_valid_signature(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['license_key' => $this->license->license_key]);
        $signature = $this->computeSignature('test-secret-key-12345', 'POST', '/_test/signature', $body, $timestamp);

        $response = $this->postJson('/_test/signature', ['license_key' => $this->license->license_key], [
            'X-Signature' => $signature,
            'X-Signature-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_rejects_missing_signature(): void
    {
        $response = $this->postJson('/_test/signature', [], [
            'X-Signature-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'MISSING_SIGNATURE');
    }

    public function test_rejects_missing_timestamp(): void
    {
        $response = $this->postJson('/_test/signature', [], [
            'X-Signature' => 'dGVzdA==',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'MISSING_SIGNATURE');
    }

    public function test_rejects_expired_signature(): void
    {
        $timestamp = (string) (time() - 600);
        $body = json_encode(['license_key' => $this->license->license_key]);
        $signature = $this->computeSignature('test-secret-key-12345', 'POST', '/_test/signature', $body, $timestamp);

        $response = $this->postJson('/_test/signature', ['license_key' => $this->license->license_key], [
            'X-Signature' => $signature,
            'X-Signature-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'SIGNATURE_EXPIRED');
    }

    public function test_rejects_mismatched_signature(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['license_key' => $this->license->license_key]);
        // 使用错误密钥签名
        $signature = $this->computeSignature('wrong-secret', 'POST', '/_test/signature', $body, $timestamp);

        $response = $this->postJson('/_test/signature', ['license_key' => $this->license->license_key], [
            'X-Signature' => $signature,
            'X-Signature-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'SIGNATURE_MISMATCH');
    }

    /**
     * P0 回归：无 metadata.signature_secret 时也不得跳过校验（伪造签名必须失败）
     */
    public function test_rejects_forged_signature_when_license_has_no_explicit_secret(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'pending',
            'metadata' => null,
        ]);

        $timestamp = (string) time();
        $body = json_encode([
            'license_key' => $license->license_key,
            'fingerprint' => 'fp-test-device-001',
        ]);
        $forged = str_repeat('a', 64);

        $response = $this->call(
            'POST',
            '/_test/signature',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => $forged,
                'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
            ],
            $body
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('SIGNATURE_MISMATCH', $response->getContent());
    }

    public function test_rejects_forged_signature_on_real_activate_route(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'pending',
            'metadata' => null,
        ]);

        $timestamp = (string) time();
        $nonce = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'license_key' => $license->license_key,
            'fingerprint' => 'fp-idor-test-' . uniqid(),
            'platform' => 'linux',
        ];
        $body = json_encode($payload);

        $response = $this->call(
            'POST',
            '/api/license/activate',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => str_repeat('b', 64),
                'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
                'HTTP_X_NONCE' => $nonce,
                'HTTP_X_TIMESTAMP' => $timestamp,
            ],
            $body
        );

        $this->assertSame(401, $response->getStatusCode(), $response->getContent());
        $this->assertTrue(
            str_contains($response->getContent(), 'SIGNATURE_MISMATCH')
            || str_contains($response->getContent(), 'MISSING_SIGNATURE')
            || str_contains($response->getContent(), 'SIGNATURE'),
            $response->getContent()
        );
        $this->assertDatabaseMissing('licenses', [
            'id' => $license->id,
            'status' => 'active',
        ]);
    }

    public function test_allows_get_requests(): void
    {
        Route::get('/_test/signature-get', function () {
            return response()->json(['success' => true]);
        })->middleware(SignatureMiddleware::class);

        $response = $this->getJson('/_test/signature-get');
        $response->assertStatus(200);
    }

    public function test_passes_with_nonce_in_canonical_string(): void
    {
        $timestamp = (string) time();
        $body = json_encode(['license_key' => $this->license->license_key]);
        $nonce = '550e8400-e29b-41d4-a716-446655440001';
        // 签名时包含 nonce
        $signature = $this->computeSignature('test-secret-key-12345', 'POST', '/_test/signature', $body, $timestamp, $nonce);

        $response = $this->postJson('/_test/signature', ['license_key' => $this->license->license_key], [
            'X-Signature' => $signature,
            'X-Signature-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
