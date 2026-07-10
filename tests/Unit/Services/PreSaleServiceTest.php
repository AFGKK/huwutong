<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PreSaleCampaign;
use App\Models\PreSaleOrder;
use App\Models\PreSaleUpdate;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\MockPaymentGateway;
use App\Services\PaymentManager;
use App\Services\PreSaleService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PreSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PreSaleService $service;
    protected Tenant $tenant;
    protected Product $product;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PreSaleService::class);
        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function it_can_create_a_pre_sale_campaign()
    {
        $campaign = $this->service->createCampaign([
            'tenant_id' => $this->tenant->id,
            'type' => 'pre_sale',
            'name' => 'Early Bird Pre-sale',
            'product_id' => $this->product->id,
            'target_amount' => 100000,
            'deposit_rate' => 20,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(PreSaleCampaign::class, $campaign);
        $this->assertEquals('draft', $campaign->status);
        $this->assertEquals(0, $campaign->raised_amount);
        $this->assertNotEmpty($campaign->slug);
    }

    /** @test */
    public function it_can_create_a_crowdfunding_campaign()
    {
        $campaign = $this->service->createCampaign([
            'tenant_id' => $this->tenant->id,
            'type' => 'crowdfunding',
            'name' => 'Community Fund',
            'product_id' => $this->product->id,
            'target_amount' => 500000,
            'target_backers' => 200,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(45),
        ]);

        $this->assertEquals('crowdfunding', $campaign->type);
        $this->assertEquals(200, $campaign->target_backers);
    }

    /** @test */
    public function it_can_publish_a_campaign()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'draft',
        ]);

        $published = $this->service->publishCampaign($campaign->id);
        $this->assertEquals('active', $published->status);
    }

    /** @test */
    public function it_cannot_publish_an_already_active_campaign()
    {
        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->publishCampaign($campaign->id);
    }

    /** @test */
    public function it_can_update_a_draft_campaign()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'draft',
            'name' => 'Original Name',
        ]);

        $updated = $this->service->updateCampaign($campaign->id, ['name' => 'Updated Name']);
        $this->assertEquals('Updated Name', $updated->name);
    }

    /** @test */
    public function it_can_cancel_an_active_campaign()
    {
        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $cancelled = $this->service->cancelCampaign($campaign->id, 'Strategy change');
        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Strategy change', $cancelled->fail_reason);
    }

    /** @test */
    public function it_can_check_and_update_status_for_ended_campaign()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'raised_amount' => 50000,
            'target_amount' => 10000,
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDay(),
        ]);

        $checked = $this->service->checkCampaignStatus($campaign->id);
        $this->assertEquals('success', $checked->status);
    }

    /** @test */
    public function it_marks_as_failed_when_target_not_reached()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'raised_amount' => 5000,
            'target_amount' => 10000,
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDay(),
        ]);

        $checked = $this->service->checkCampaignStatus($campaign->id);
        $this->assertEquals('failed', $checked->status);
    }

    /** @test */
    public function it_can_complete_a_successful_campaign()
    {
        $campaign = PreSaleCampaign::factory()->success()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $completed = $this->service->completeCampaign($campaign->id);
        $this->assertEquals('completed', $completed->status);
    }

    /** @test */
    public function it_can_place_an_order()
    {
        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'deposit_rate' => 30,
            'deposit_amount' => 0,
        ]);

        $order = $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'quantity' => 2,
            'total_amount' => 1000,
        ]);

        $this->assertInstanceOf(PreSaleOrder::class, $order);
        $this->assertEquals('deposit_pending', $order->payment_status);
        $this->assertEquals(0, $order->deposit_paid);
        $this->assertEquals(1, $campaign->fresh()->current_backers);
    }

    /** @test */
    public function it_can_pay_deposit()
    {
        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'deposit_rate' => 20,
            'deposit_amount' => 0,
        ]);

        $order = $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'total_amount' => 1000,
        ]);

        $paid = $this->service->payDeposit($order->id);
        $this->assertEquals('deposit_paid', $paid->payment_status);
        $this->assertTrue($paid->deposit_paid > 0);
        $this->assertNotNull($paid->deposit_paid_at);
    }

    /** @test */
    public function it_can_pay_final_payment()
    {
        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'deposit_rate' => 30,
            'deposit_amount' => 0,
        ]);

        $order = $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'total_amount' => 2000,
        ]);

        $this->service->payDeposit($order->id);
        $paid = $this->service->payFinal($order->id);

        $this->assertEquals('final_paid', $paid->payment_status);
        $this->assertNotNull($paid->final_paid_at);
    }

    /** @test */
    public function it_can_post_and_retrieve_updates()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $update = $this->service->postUpdate($campaign->id, [
            'title' => 'Progress Update',
            'content' => 'We have reached 50% of our goal!',
            'type' => 'milestone',
            'is_pinned' => true,
        ]);

        $this->assertInstanceOf(PreSaleUpdate::class, $update);
        $this->assertEquals('Progress Update', $update->title);
        $this->assertTrue($update->is_pinned);

        $updates = $this->service->getUpdates($campaign->id);
        $this->assertCount(1, $updates);
    }

    /** @test */
    public function it_can_list_campaigns_with_filters()
    {
        PreSaleCampaign::factory()->count(3)->preSale()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);
        PreSaleCampaign::factory()->count(2)->crowdfunding()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $result = $this->service->listCampaigns(['type' => 'pre_sale', 'per_page' => 20]);
        $this->assertEquals(3, $result->total());

        $result2 = $this->service->listCampaigns(['type' => 'crowdfunding', 'per_page' => 20]);
        $this->assertEquals(2, $result2->total());
    }

    /** @test */
    public function it_returns_correct_campaign_attributes()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'crowdfunding',
            'raised_amount' => 30000,
            'target_amount' => 100000,
            'start_at' => now()->subDays(5),
            'end_at' => now()->addDays(25),
            'status' => 'active',
        ]);

        $this->assertTrue($campaign->isActive());
        $this->assertTrue($campaign->is_crowdfunding);
        $this->assertFalse($campaign->is_pre_sale);
        $this->assertEquals(30.0, $campaign->progressPercent);
        $this->assertGreaterThanOrEqual(24, $campaign->remainingDays);
        $this->assertFalse($campaign->hasEnded());
    }

    /** @test */
    public function it_can_delete_a_draft_campaign()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'draft',
        ]);

        $this->service->deleteCampaign($campaign->id);
        $this->assertNull(PreSaleCampaign::find($campaign->id));
    }

    /** @test */
    public function it_cannot_place_order_on_inactive_campaign()
    {
        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'draft',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_creates_invoice_when_paying_deposit()
    {
        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'deposit_rate' => 20,
            'deposit_amount' => 0,
        ]);

        $order = $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'total_amount' => 1000,
        ]);

        $paid = $this->service->payDeposit($order->id, 'gateway');

        $this->assertEquals('deposit_paid', $paid->payment_status);
        $this->assertEquals(200, (float) $paid->deposit_paid);
        $this->assertNotNull($paid->payment_meta['deposit_invoice_id']);

        $invoice = Invoice::find($paid->payment_meta['deposit_invoice_id']);
        $this->assertNotNull($invoice);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(200, (float) $invoice->amount);
    }

    /** @test */
    public function it_fails_deposit_when_gateway_declines()
    {
        app(PaymentManager::class)->setGateway(new MockPaymentGateway(0.0));

        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'deposit_rate' => 20,
        ]);

        $order = $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'total_amount' => 1000,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->payDeposit($order->id);
    }

    /** @test */
    public function it_refunds_paid_orders_when_campaign_cancelled()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $campaign = PreSaleCampaign::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'deposit_rate' => 20,
            'raised_amount' => 0,
        ]);

        $order = $this->service->placeOrder([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'total_amount' => 1000,
        ]);

        $this->service->payDeposit($order->id);

        $this->service->cancelCampaign($campaign->id, '测试取消');

        $order->refresh();
        $this->assertEquals('refunded', $order->payment_status);
        $this->assertEquals(0, (float) $campaign->fresh()->raised_amount);
    }

    /** @test */
    public function it_refunds_orders_when_crowdfunding_fails()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $campaign = PreSaleCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'crowdfunding',
            'status' => 'active',
            'raised_amount' => 500,
            'target_amount' => 10000,
            'deposit_rate' => 100,
            'deposit_amount' => 0,
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDay(),
            'settings' => ['refund_on_fail' => true],
        ]);

        $order = PreSaleOrder::factory()->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $this->user->id,
            'total_amount' => 500,
            'deposit_paid' => 500,
            'payment_status' => 'deposit_paid',
            'payment_meta' => [
                'deposit_invoice_id' => Invoice::create([
                    'tenant_id' => $this->tenant->id,
                    'customer_id' => $customer->id,
                    'invoice_no' => 'INV-TEST-1',
                    'amount' => 500,
                    'currency' => 'CNY',
                    'status' => 'paid',
                    'paid_at' => now(),
                    'billing_reason' => 'pre_sale_deposit',
                ])->id,
                'deposit_payment_method' => 'mock',
            ],
        ]);

        $checked = $this->service->checkCampaignStatus($campaign->id);

        $this->assertEquals('failed', $checked->status);
        $this->assertEquals('refunded', $order->fresh()->payment_status);
    }
}
