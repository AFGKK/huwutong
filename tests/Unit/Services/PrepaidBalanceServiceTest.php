<?php

namespace Tests\Unit\Services;

use App\Models\CreditLimit;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PrepaidBalance;
use App\Models\PrepaidTransaction;
use App\Models\Tenant;
use App\Services\PrepaidBalanceService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PrepaidBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PrepaidBalanceService $service;
    protected Tenant $tenant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PrepaidBalanceService::class);
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_it_creates_balance_on_first_access(): void
    {
        $balance = $this->service->getBalance($this->customer);

        $this->assertNotNull($balance);
        $this->assertEquals(0, (float) $balance->balance);
        $this->assertEquals('active', $balance->status);
    }

    public function test_it_returns_existing_balance(): void
    {
        $balance = $this->service->getBalance($this->customer);
        $sameBalance = $this->service->getBalance($this->customer);

        $this->assertEquals($balance->id, $sameBalance->id);
    }

    public function test_admin_recharge_increases_balance(): void
    {
        $transaction = $this->service->adminRecharge($this->customer, 1000);

        $this->assertNotNull($transaction);
        $this->assertEquals(1000, (float) $transaction->amount);
        $this->assertEquals(0, (float) $transaction->balance_before);
        $this->assertEquals(1000, (float) $transaction->balance_after);

        $balance = $this->service->getBalance($this->customer);
        $this->assertEquals(1000, (float) $balance->balance);
        $this->assertEquals(1000, (float) $balance->total_recharged);
    }

    public function test_consume_reduces_balance(): void
    {
        $this->service->adminRecharge($this->customer, 500);

        $result = $this->service->consume($this->customer, 200);

        $this->assertTrue($result['success']);
        $this->assertEquals(300, $result['balance_after']);

        $balance = $this->service->getBalance($this->customer);
        $this->assertEquals(300, (float) $balance->balance);
        $this->assertEquals(200, (float) $balance->total_consumed);
    }

    public function test_consume_fails_when_insufficient_balance(): void
    {
        $this->service->adminRecharge($this->customer, 100);

        $result = $this->service->consume($this->customer, 200);

        $this->assertFalse($result['success']);
        $this->assertEquals('余额不足', $result['error']);
    }

    public function test_refund_increases_balance(): void
    {
        $this->service->adminRecharge($this->customer, 500);
        $this->service->consume($this->customer, 200);

        $transaction = $this->service->refund($this->customer, 100);

        $this->assertEquals(100, (float) $transaction->amount);

        $balance = $this->service->getBalance($this->customer);
        $this->assertEquals(400, (float) $balance->balance);
    }

    public function test_adjust_increases_balance(): void
    {
        $this->service->adminRecharge($this->customer, 500);

        $result = $this->service->adjust($this->customer, 200);

        $this->assertTrue($result['success']);
        $this->assertEquals(700, $result['balance_after']);
    }

    public function test_adjust_decreases_balance(): void
    {
        $this->service->adminRecharge($this->customer, 500);

        $result = $this->service->adjust($this->customer, -200);

        $this->assertTrue($result['success']);
        $this->assertEquals(300, $result['balance_after']);
    }

    public function test_adjust_fails_when_negative_result(): void
    {
        $this->service->adminRecharge($this->customer, 100);

        $result = $this->service->adjust($this->customer, -200);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('负数', $result['error']);
    }

    public function test_recharge_multiple_times(): void
    {
        $this->service->adminRecharge($this->customer, 300);
        $this->service->adminRecharge($this->customer, 500);

        $balance = $this->service->getBalance($this->customer);
        $this->assertEquals(800, (float) $balance->balance);
        $this->assertEquals(800, (float) $balance->total_recharged);
    }

    public function test_get_available_funds(): void
    {
        $this->service->adminRecharge($this->customer, 500);
        $this->service->setCreditLimit($this->customer, 300);

        $funds = $this->service->getAvailableFunds($this->customer);

        $this->assertEquals(500, $funds['balance']);
        $this->assertEquals(300, $funds['credit_limit']);
        $this->assertEquals(0, $funds['credit_used']);
        $this->assertEquals(300, $funds['available_credit']);
        $this->assertEquals(800, $funds['total_available']);
    }

    public function test_credit_limit_setting(): void
    {
        $credit = $this->service->setCreditLimit($this->customer, 1000, 7);

        $this->assertEquals(1000, (float) $credit->credit_limit);
        $this->assertEquals(7, $credit->grace_days);
        $this->assertEquals('active', $credit->status);
    }

    public function test_credit_use_and_repay(): void
    {
        $this->service->setCreditLimit($this->customer, 500, 3);

        // 使用信用额度
        $useResult = $this->service->useCredit($this->customer, 200);
        $this->assertTrue($useResult['success']);
        $this->assertEquals(200, $useResult['used_credit']);
        $this->assertEquals(300, $useResult['available_credit']);

        // 偿还
        $repayResult = $this->service->repayCredit($this->customer, 100);
        $this->assertTrue($repayResult['success']);
        $this->assertEquals(100, $repayResult['used_credit']);
        $this->assertEquals(400, $repayResult['available_credit']);
    }

    public function test_use_credit_fails_when_insufficient(): void
    {
        $this->service->setCreditLimit($this->customer, 100);

        $result = $this->service->useCredit($this->customer, 200);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('不足', $result['error']);
    }

    public function test_auto_recharge_settings(): void
    {
        $this->service->saveAutoRechargeSettings($this->customer, true, 50, 200, 'alipay');

        $settings = $this->service->getAutoRechargeSettings($this->customer);

        $this->assertNotNull($settings);
        $this->assertTrue($settings['enabled']);
        $this->assertEquals(50, $settings['threshold']);
        $this->assertEquals(200, $settings['amount']);
        $this->assertEquals('alipay', $settings['payment_method']);
    }

    public function test_check_auto_recharge_does_not_trigger_when_above_threshold(): void
    {
        $this->service->adminRecharge($this->customer, 500);
        $this->service->saveAutoRechargeSettings($this->customer, true, 100, 200, 'alipay');

        $result = $this->service->checkAutoRecharge($this->customer);

        $this->assertNull($result);
    }

    public function test_get_transactions(): void
    {
        $this->service->adminRecharge($this->customer, 500);
        $this->service->consume($this->customer, 200);

        $transactions = $this->service->getTransactions($this->customer);
        $items = $transactions->items();

        $this->assertCount(2, $items);
        $this->assertEquals('consume', $items[0]->type);
        $this->assertEquals('recharge', $items[1]->type);
    }

    public function test_pay_invoice_with_balance(): void
    {
        $this->service->adminRecharge($this->customer, 1000);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 300,
            'status' => 'pending',
        ]);

        $result = $this->service->payInvoiceWithBalance($invoice);

        $this->assertTrue($result['success']);
        $this->assertEquals('prepaid', $result['method']);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_get_stats(): void
    {
        // Create another customer in same tenant
        $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->adminRecharge($this->customer, 1000);
        $this->service->adminRecharge($customer2, 500);
        $this->service->consume($this->customer, 300);
        $this->service->setCreditLimit($this->customer, 2000);

        $stats = $this->service->getStats($this->tenant->id);

        $this->assertEquals(1200, $stats['total_balance']);
        $this->assertEquals(300, $stats['total_consumed']);
        $this->assertEquals(1500, $stats['total_recharged']);
        $this->assertEquals(2, $stats['active_accounts']);
        $this->assertEquals(2000, $stats['credit']['total_limit']);
    }
}
