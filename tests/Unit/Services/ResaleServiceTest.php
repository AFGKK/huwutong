<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\ResaleListing;
use App\Models\ResaleTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ResaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ResaleService $service;
    protected Tenant $tenant;
    protected Customer $seller;
    protected License $license;

    protected function setUp(): void
    {
        parent::setUp();

        // OwnershipTransferService 需要这些
        $this->tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create();

        $this->seller = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $admin->id,
        ]);

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'customer_id' => $this->seller->id,
            'status' => 'active',
            'license_key' => 'RS-TEST-' . uniqid(),
        ]);

        $this->service = app(ResaleService::class);
    }

    /** @test */
    public function it_creates_a_resale_listing()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            [
                'title' => '专业版 License 转售',
                'description' => '使用半年的专业版 License',
                'asking_price' => 2999.00,
                'currency' => 'CNY',
            ],
        );

        $this->assertNotNull($listing);
        $this->assertEquals('专业版 License 转售', $listing->title);
        $this->assertEquals(2999.00, $listing->asking_price);
        $this->assertEquals(ResaleListing::STATUS_DRAFT, $listing->status);
        $this->assertStringStartsWith('RSL-', $listing->reference);
    }

    /** @test */
    public function it_rejects_creating_listing_for_expired_license()
    {
        $this->license->update(['status' => 'expired']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('不符合转售条件');

        $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '过期 License', 'asking_price' => 100],
        );
    }

    /** @test */
    public function it_rejects_creating_duplicate_listing()
    {
        $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '第一个挂牌', 'asking_price' => 100],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('已有进行中的挂牌');

        $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '重复挂牌', 'asking_price' => 200],
        );
    }

    /** @test */
    public function it_publishes_listing_for_review()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '测试挂牌', 'asking_price' => 1000],
        );

        $published = $this->service->publishListing($listing->id);

        $this->assertEquals(ResaleListing::STATUS_PENDING_REVIEW, $published->status);
    }

    /** @test */
    public function it_approves_listing()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '待审核挂牌', 'asking_price' => 500],
        );

        $this->service->publishListing($listing->id);
        $reviewed = $this->service->reviewListing($listing->id, 1, 'approve', '审核通过');

        $this->assertEquals(ResaleListing::STATUS_ACTIVE, $reviewed->status);
        $this->assertNotNull($reviewed->reviewed_at);
    }

    /** @test */
    public function it_rejects_listing()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '不合格挂牌', 'asking_price' => 500],
        );

        $this->service->publishListing($listing->id);
        $reviewed = $this->service->reviewListing($listing->id, 1, 'reject', '信息不完整');

        $this->assertEquals(ResaleListing::STATUS_DRAFT, $reviewed->status);
        $this->assertEquals('信息不完整', $reviewed->review_notes);
    }

    /** @test */
    public function it_cancels_listing()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '要取消的挂牌', 'asking_price' => 500],
        );

        $cancelled = $this->service->cancelListing($listing->id);

        $this->assertEquals(ResaleListing::STATUS_CANCELLED, $cancelled->status);
    }

    /** @test */
    public function it_browses_marketplace()
    {
        // 创建一些挂牌
        for ($i = 1; $i <= 3; $i++) {
            $lic = License::factory()->create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->license->product_id,
                'customer_id' => $this->seller->id,
                'status' => 'active',
                'license_key' => 'BROWSE-' . $i,
            ]);

            $listing = $this->service->createListing(
                $this->tenant->id,
                $lic->id,
                $this->seller->id,
                ['title' => "商品 {$i}", 'asking_price' => $i * 1000],
            );

            // 直接发布+审核通过
            $listing->update(['status' => ResaleListing::STATUS_PENDING_REVIEW]);
            $listing->update(['status' => ResaleListing::STATUS_ACTIVE, 'listed_at' => now()]);
        }

        $result = $this->service->browseMarketplace($this->tenant->id);

        $this->assertCount(3, $result['items']);
        $this->assertEquals(3, $result['total']);
    }

    /** @test */
    public function it_creates_transaction_on_purchase()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '可购买', 'asking_price' => 2000, 'commission_rate' => 5.00],
        );

        $listing->update(['status' => ResaleListing::STATUS_ACTIVE, 'listed_at' => now()]);

        $buyer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = $this->service->purchaseListing($listing->id, $buyer->id);

        $this->assertNotNull($transaction);
        $this->assertEquals(2000, $transaction->agreed_price);
        $this->assertEquals(100, $transaction->commission_amount); // 5% of 2000
        $this->assertEquals(1900, $transaction->seller_payout);
        $this->assertEquals(ResaleTransaction::STATUS_PENDING_PAYMENT, $transaction->status);

        // 验证挂牌已标记为已售
        $listing->refresh();
        $this->assertEquals(ResaleListing::STATUS_SOLD, $listing->status);
    }

    /** @test */
    public function it_confirms_payment()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '付款测试', 'asking_price' => 1500],
        );

        $listing->update(['status' => ResaleListing::STATUS_ACTIVE, 'listed_at' => now()]);

        $buyer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $transaction = $this->service->purchaseListing($listing->id, $buyer->id);

        $confirmed = $this->service->confirmPayment($transaction->id, 'alipay', 'PAY-123456');

        $this->assertEquals(ResaleTransaction::STATUS_PAID, $confirmed->status);
        $this->assertNotNull($confirmed->paid_at);
        $this->assertEquals('alipay', $confirmed->payment_method);
    }

    /** @test */
    public function it_gets_sellable_licenses()
    {
        $sellable = $this->service->getSellableLicenses($this->tenant->id, $this->seller->id);

        // should include the license created in setUp
        $this->assertCount(1, $sellable);

        // once listed, it should no longer appear
        $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '已挂牌', 'asking_price' => 100],
        );

        $sellableAfter = $this->service->getSellableLicenses($this->tenant->id, $this->seller->id);
        $this->assertCount(0, $sellableAfter);
    }

    /** @test */
    public function it_gets_market_stats()
    {
        $stats = $this->service->getMarketStats($this->tenant->id);
        $this->assertArrayHasKey('active_listings', $stats);
        $this->assertArrayHasKey('total_sold', $stats);
    }

    /** @test */
    public function it_calculates_estimated_commission()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            [
                'title' => '佣金计算测试',
                'asking_price' => 1000,
                'commission_rate' => 8.00,
            ],
        );

        $this->assertEquals(80, $listing->estimated_commission);
        $this->assertEquals(920, $listing->estimated_payout);
    }

    /** @test */
    public function it_cancels_transaction_and_restores_listing()
    {
        $listing = $this->service->createListing(
            $this->tenant->id,
            $this->license->id,
            $this->seller->id,
            ['title' => '交易取消测试', 'asking_price' => 1000],
        );
        $listing->update(['status' => ResaleListing::STATUS_ACTIVE, 'listed_at' => now()]);

        $buyer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $transaction = $this->service->purchaseListing($listing->id, $buyer->id);

        // 取消交易
        $cancelled = $this->service->cancelTransaction($transaction->id, 1);
        $this->assertEquals(ResaleTransaction::STATUS_CANCELLED, $cancelled->status);

        // 验证挂牌已恢复
        $listing->refresh();
        $this->assertEquals(ResaleListing::STATUS_ACTIVE, $listing->status);
        $this->assertNull($listing->sold_at);
    }
}
