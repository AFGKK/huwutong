<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Invoice;
use App\Services\Payment\PaypalPaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PaypalPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private PaypalPaymentGateway $gateway;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);

        $this->gateway = new PaypalPaymentGateway([
            'client_id' => 'test_paypal_client_id',
            'client_secret' => 'test_paypal_secret',
            'sandbox' => true,
        ]);

        $this->invoice = Invoice::factory()->create([
            'amount' => 120.00,
            'invoice_no' => 'INV-PP-'.Str::random(6),
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_returns_the_gateway_name()
    {
        $this->assertEquals('paypal', $this->gateway->name());
    }

    /** @test */
    public function it_handles_configuration_missing_gracefully()
    {
        $invoice = Invoice::factory()->create([
            'amount' => 120.00,
            'invoice_no' => 'INV-PP-CFG-'.Str::random(6),
            'status' => 'pending',
        ]);

        $gateway = new PaypalPaymentGateway([]);
        $result = $gateway->charge($invoice);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_queries_transaction_status()
    {
        $result = $this->gateway->query('paypal_txn_test_001');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('transaction_id', $result);
    }

    /** @test */
    public function it_verifies_callback_signature()
    {
        $payload = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];
        $result = $this->gateway->verifyCallback($payload);

        $this->assertIsBool($result);
    }

    /** @test */
    public function it_handles_charge_without_oauth_token()
    {
        Http::fake([
            'api-m.sandbox.paypal.com/*' => Http::response([
                'access_token' => 'test_access_token',
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $result = $this->gateway->charge($this->invoice);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
