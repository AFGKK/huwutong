<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingHistoryPortalService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BillingHistoryPortalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BillingHistoryPortalService $service;
    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BillingHistoryPortalService::class);
        $this->tenant = Tenant::factory()->create(['name' => '测试租户']);
        $this->user = User::factory()->create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'enterprise',
        ]);
    }

    protected function createInvoice(array $overrides = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 100.00,
            'subtotal' => 100.00,
            'currency' => 'CNY',
            'status' => 'paid',
            'paid' => true,
            'billing_reason' => 'subscription_create',
            'payment_method' => 'alipay',
        ], $overrides));
    }

    /** @test */
    public function it_returns_paginated_invoices()
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createInvoice(['invoice_no' => 'INV-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT)]);
        }

        $result = $this->service->getInvoices($this->tenant, $this->customer, [], 10);

        $this->assertEquals(15, $result->total());
        $this->assertCount(10, $result->items());
    }

    /** @test */
    public function it_filters_invoices_by_status()
    {
        $this->createInvoice(['invoice_no' => 'INV-000001', 'status' => 'paid']);
        $this->createInvoice(['invoice_no' => 'INV-000002', 'status' => 'pending']);

        $result = $this->service->getInvoices($this->tenant, $this->customer, ['status' => 'paid']);
        $this->assertEquals(1, $result->total());

        $result = $this->service->getInvoices($this->tenant, $this->customer, ['status' => 'pending']);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_filters_invoices_by_date_range()
    {
        $this->travelTo(now()->subDays(5));
        $this->createInvoice(['invoice_no' => 'INV-000001']);
        $this->travelBack();

        $this->createInvoice(['invoice_no' => 'INV-000002']);

        // 只查今天
        $result = $this->service->getInvoices($this->tenant, $this->customer, [
            'date_from' => now()->startOfDay()->toDateString(),
        ]);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_filters_by_billing_reason()
    {
        $this->createInvoice(['invoice_no' => 'INV-000001', 'billing_reason' => 'subscription_create']);
        $this->createInvoice(['invoice_no' => 'INV-000002', 'billing_reason' => 'renewal']);

        $result = $this->service->getInvoices($this->tenant, $this->customer, ['billing_reason' => 'renewal']);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_returns_invoice_detail()
    {
        $invoice = $this->createInvoice();

        $found = $this->service->getInvoiceDetail($this->tenant, $this->customer, $invoice->id);
        $this->assertNotNull($found);
        $this->assertEquals($invoice->id, $found->id);

        // 其他客户看不到
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $notFound = $this->service->getInvoiceDetail($otherTenant, $otherCustomer, $invoice->id);
        $this->assertNull($notFound);
    }

    /** @test */
    public function it_returns_stats()
    {
        $this->createInvoice(['amount' => 100.00, 'status' => 'paid']);
        $this->createInvoice(['amount' => 200.00, 'status' => 'paid']);
        $this->createInvoice(['amount' => 50.00, 'status' => 'pending']);

        $stats = $this->service->getStats($this->tenant, $this->customer);

        $this->assertEquals(3, $stats['total_invoices']);
        $this->assertEquals(300.00, $stats['total_revenue']);
        $this->assertEquals(50.00, $stats['pending_amount']);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_payment_method', $stats);
        $this->assertArrayHasKey('by_billing_reason', $stats);
    }

    /** @test */
    public function it_returns_subscriptions()
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'plan' => '专业版',
            'status' => 'active',
            'price' => 199.00,
            'currency' => 'CNY',
            'billing_period' => 'monthly',
        ]);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'plan' => '企业版',
            'status' => 'active',
            'price' => 499.00,
            'currency' => 'CNY',
            'billing_period' => 'yearly',
        ]);

        $subscriptions = $this->service->getSubscriptions($this->tenant, $this->customer);

        $this->assertCount(2, $subscriptions);
    }

    /** @test */
    public function it_returns_failed_payments()
    {
        // 逾期账单
        $this->createInvoice([
            'invoice_no' => 'INV-OVERDUE',
            'status' => 'pending',
            'due_at' => now()->subDays(3),
        ]);

        // 退款
        $invoice = $this->createInvoice(['invoice_no' => 'INV-REFUND']);
        Refund::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'reason' => '客户要求退款',
            'status' => 'completed',
        ]);

        $result = $this->service->getFailedPayments($this->tenant, $this->customer);

        $this->assertCount(1, $result['overdue_invoices']);
        $this->assertCount(1, $result['refunds']);
        $this->assertEquals('INV-OVERDUE', $result['overdue_invoices'][0]['invoice_no']);
    }

    /** @test */
    public function it_returns_auto_renewal_records()
    {
        $this->createInvoice([
            'invoice_no' => 'INV-RENEW-001',
            'billing_reason' => 'renewal',
            'status' => 'paid',
            'amount' => 199.00,
        ]);
        $this->createInvoice([
            'invoice_no' => 'INV-RENEW-002',
            'billing_reason' => 'renewal',
            'status' => 'pending',
            'amount' => 199.00,
        ]);

        $result = $this->service->getAutoRenewalRecords($this->tenant, $this->customer);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['success_count']);
        $this->assertEquals(1, $result['failed_count']);
    }

    /** @test */
    public function it_returns_filter_options()
    {
        $options = $this->service->getFilterOptions();

        $this->assertArrayHasKey('statuses', $options);
        $this->assertArrayHasKey('billing_reasons', $options);
        $this->assertArrayHasKey('payment_methods', $options);
        $this->assertArrayHasKey('sort_options', $options);
        $this->assertEquals('已支付', $options['statuses']['paid']);
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        $customer2 = Customer::factory()->create(['tenant_id' => $tenant2->id, 'user_id' => $user2->id]);

        $this->createInvoice(['invoice_no' => 'INV-T1']);
        Invoice::factory()->create([
            'tenant_id' => $tenant2->id,
            'customer_id' => $customer2->id,
            'invoice_no' => 'INV-T2',
            'amount' => 200.00,
            'status' => 'paid',
        ]);

        // 租户1只看得到自己的
        $result1 = $this->service->getInvoices($this->tenant, $this->customer);
        $this->assertEquals(1, $result1->total());
        $this->assertEquals('INV-T1', $result1->first()->invoice_no);

        // 租户2只看得到自己的
        $result2 = $this->service->getInvoices($tenant2, $customer2);
        $this->assertEquals(1, $result2->total());
        $this->assertEquals('INV-T2', $result2->first()->invoice_no);
    }

    /** @test */
    public function it_provides_status_helper_methods()
    {
        $this->assertEquals('已支付', BillingHistoryPortalService::getStatusLabel('paid'));
        $this->assertEquals('待支付', BillingHistoryPortalService::getStatusLabel('pending'));
        $this->assertEquals('已退款', BillingHistoryPortalService::getStatusLabel('refunded'));

        $this->assertEquals('success', BillingHistoryPortalService::getStatusType('paid'));
        $this->assertEquals('warning', BillingHistoryPortalService::getStatusType('pending'));
        $this->assertEquals('danger', BillingHistoryPortalService::getStatusType('refunded'));

        $this->assertEquals('自动续费', BillingHistoryPortalService::getBillingReasonLabel('renewal'));
        $this->assertEquals('订阅创建', BillingHistoryPortalService::getBillingReasonLabel('subscription_create'));
    }
}
