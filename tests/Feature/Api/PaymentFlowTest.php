<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Payment\MockPaymentGateway;
use App\Services\PaymentManager;
use App\Services\PortalInvoicePaymentService;
use Tests\Concerns\RefreshDatabase;
use Tests\Support\AsyncPaymentGateway;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function mock_gateway_marks_invoice_paid_synchronously(): void
    {
        app(PaymentManager::class)->setGateway(new MockPaymentGateway(1.0));

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 88,
            'status' => 'pending',
        ]);

        $result = app(BillingService::class)->processPayment($invoice);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['async'] ?? true);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    /** @test */
    public function async_gateway_keeps_invoice_pending_until_webhook(): void
    {
        app(PaymentManager::class)->setGateway(new AsyncPaymentGateway());

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 128,
            'status' => 'pending',
        ]);

        $result = app(BillingService::class)->processPayment($invoice);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['async']);
        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertSame('async_txn_test_001', $invoice->fresh()->metadata['pending_transaction_id'] ?? null);
    }

    /** @test */
    public function portal_payment_returns_pending_for_async_gateway(): void
    {
        app(PaymentManager::class)->setGateway(new AsyncPaymentGateway());

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 99,
            'status' => 'pending',
        ]);

        $result = app(PortalInvoicePaymentService::class)->payInvoice($this->customer, $invoice);

        $this->assertSame('pending', $result['status']);
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    /** @test */
    public function alipay_webhook_marks_invoice_and_order_paid(): void
    {
        $order = Order::create([
            'order_no' => 'HWT20260712TEST01',
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 199,
            'discount_amount' => 0,
            'final_amount' => 199,
            'currency' => 'CNY',
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_no' => 'INV-'.$order->order_no,
            'amount' => 199,
            'status' => 'pending',
            'metadata' => ['order_id' => $order->id, 'order_no' => $order->order_no],
        ]);

        $response = $this->postJson('/api/payment/alipay/webhook', [
            'notify_type' => 'trade_status_sync',
            'notify_id' => 'notify_test_001',
            'out_trade_no' => $invoice->invoice_no,
            'trade_no' => '2026071222001234567890',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '199.00',
            'sign' => 'test-sign',
            'sign_type' => 'RSA2',
        ]);

        $response->assertOk();
        $response->assertSee('success');
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    /** @test */
    public function stripe_webhook_marks_invoice_paid_by_metadata(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 50,
            'status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_test_payment_success',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'metadata' => [
                        'invoice_id' => (string) $invoice->id,
                    ],
                    'charges' => [
                        'data' => [
                            ['id' => 'ch_test_123'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/payment/stripe/webhook', $payload, [
            'Stripe-Signature' => 't=test,v1=test',
        ])->assertOk();

        $this->assertSame('paid', $invoice->fresh()->status);
    }
}
