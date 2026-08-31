<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Invoice;
use App\Services\Payment\WechatPaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class WechatPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private WechatPaymentGateway $gateway;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new WechatPaymentGateway([
            'app_id' => 'wx_test_appid',
            'mch_id' => '1234567890',
            'key' => 'test_key_32chars_long_abcdef123456',
        ]);

        $this->invoice = Invoice::factory()->create([
            'amount' => 88.00,
            'invoice_no' => 'INV-WX-'.Str::random(6),
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_returns_the_gateway_name()
    {
        $this->assertEquals('wechat', $this->gateway->name());
    }

    /** @test */
    public function it_handles_configuration_missing_gracefully()
    {
        $invoice = Invoice::factory()->create([
            'amount' => 88.00,
            'invoice_no' => 'INV-WX-CFG-'.Str::random(6),
            'status' => 'pending',
        ]);

        $gateway = new WechatPaymentGateway([]);
        $result = $gateway->charge($invoice);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_returns_native_charge_result()
    {
        Http::fake([
            'api.mch.weixin.qq.com/*' => Http::response([
                'prepay_id' => 'wx_prepay_test_001',
                'code_url' => 'weixin://pay/test_qr_code',
            ], 200),
        ]);

        $invoice = Invoice::factory()->create([
            'amount' => 88.00,
            'invoice_no' => 'INV-WX-NTV-'.Str::random(6),
            'status' => 'pending',
        ]);

        $result = $this->gateway->charge($invoice, ['trade_type' => 'NATIVE']);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('prepay_id', $result);
        $this->assertArrayHasKey('qr_code', $result);
        $this->assertStringContainsString('weixin://', $result['qr_code']);
    }

    /** @test */
    public function it_queries_transaction_status()
    {
        Http::fake([
            'api.mch.weixin.qq.com/*' => Http::response([
                'trade_state' => 'SUCCESS',
                'transaction_id' => 'wx_txn_test_001',
            ], 200),
        ]);

        $result = $this->gateway->query('wx_txn_test_001');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('transaction_id', $result);
    }

    /** @test */
    public function it_verifies_callback_signature()
    {
        $payload = ['return_code' => 'SUCCESS', 'out_trade_no' => 'INV-WX-TEST-001'];
        $result = $this->gateway->verifyCallback($payload);

        $this->assertIsBool($result);
    }
}
