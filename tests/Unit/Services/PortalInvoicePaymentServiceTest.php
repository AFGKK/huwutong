<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\MockPaymentGateway;
use App\Services\PaymentManager;
use App\Services\PortalInvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalInvoicePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PortalInvoicePaymentService $service;
    protected Tenant $tenant;
    protected Customer $customer;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PortalInvoicePaymentService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_pays_invoice_via_mock_gateway()
    {
        app(PaymentManager::class)->setGateway(new MockPaymentGateway(1.0));

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 99.00,
            'status' => 'pending',
            'currency' => 'CNY',
        ]);

        $result = $this->service->payInvoice($this->customer, $invoice, 'gateway');

        $this->assertEquals('paid', $result['status']);
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    /** @test */
    public function it_rejects_payment_for_non_owned_invoice()
    {
        $other = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $other->id,
            'amount' => 50,
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->payInvoice($this->customer, $invoice);
    }

    /** @test */
    public function it_rejects_already_paid_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 50,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->payInvoice($this->customer, $invoice);
    }

    /** @test */
    public function it_returns_payment_status()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 50,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $status = $this->service->getPaymentStatus($this->customer, $invoice);

        $this->assertEquals('paid', $status['status']);
        $this->assertEquals($invoice->id, $status['invoice_id']);
    }
}
