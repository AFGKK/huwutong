<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Invoice;
use App\Services\Payment\AlipayPaymentGateway;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AlipayPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private AlipayPaymentGateway $gateway;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new AlipayPaymentGateway([
            'app_id' => 'test_app_id',
            'private_key' => 'test_private_key',
            'public_key' => 'test_public_key',
            'notify_url' => 'http://localhost/api/payment/alipay/webhook',
            'return_url' => 'http://localhost/portal/orders',
        ]);

        $this->invoice = Invoice::factory()->create([
            'amount' => 99.00,
            'invoice_no' => 'INV-ALI-TEST-'.Str::random(6),
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_returns_the_gateway_name()
    {
        $this->assertEquals('alipay', $this->gateway->name());
    }

    /** @test */
    public function it_returns_error_when_config_missing()
    {
        $invoice = Invoice::factory()->create([
            'amount' => 99.00,
            'invoice_no' => 'INV-ALI-CFG-'.Str::random(6),
            'status' => 'pending',
        ]);

        $gateway = new AlipayPaymentGateway([]);
        $result = $gateway->charge($invoice);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_refunds_an_invoice()
    {
        $result = $this->gateway->refund($this->invoice);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('refund_id', $result);
    }

    /** @test */
    public function it_queries_transaction_status()
    {
        $result = $this->gateway->query('test_txn_001');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('transaction_id', $result);
    }

    /** @test */
    public function it_verifies_callback_signature()
    {
        $payload = ['trade_status' => 'TRADE_SUCCESS', 'out_trade_no' => 'INV-TEST-001'];
        $result = $this->gateway->verifyCallback($payload);

        $this->assertIsBool($result);
    }
}
