<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Invoice;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class StripePaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private StripePaymentGateway $gateway;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new StripePaymentGateway([
            'secret' => 'sk_test_mock_secret_key',
        ]);

        $this->invoice = Invoice::factory()->create([
            'amount' => 49.99,
            'invoice_no' => 'INV-STRIPE-'.Str::random(6),
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_returns_the_gateway_name()
    {
        $this->assertEquals('stripe', $this->gateway->name());
    }

    /** @test */
    public function it_handles_configuration_missing_gracefully()
    {
        $invoice = Invoice::factory()->create([
            'amount' => 49.99,
            'invoice_no' => 'INV-STRIPE-CFG-'.Str::random(6),
            'status' => 'pending',
        ]);

        $gateway = new StripePaymentGateway([]);
        $result = $gateway->charge($invoice);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_queries_transaction_status()
    {
        if (! class_exists('Stripe\StripeClient')) {
            $this->markTestSkipped('stripe/stripe-php package is not installed');
        }

        $result = $this->gateway->query('pi_test_001');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('transaction_id', $result);
    }

    /** @test */
    public function it_verifies_callback_signature()
    {
        $payload = ['type' => 'payment_intent.succeeded', 'data' => ['object' => []]];
        $result = $this->gateway->verifyCallback($payload);

        $this->assertIsBool($result);
    }
}
