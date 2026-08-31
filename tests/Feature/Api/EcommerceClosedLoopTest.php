<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\MockPaymentGateway;
use App\Services\PaymentManager;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EcommerceClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private ProductSku $sku;
    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        app(PaymentManager::class)->setGateway(new MockPaymentGateway(1.0));

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'License Product',
            'slug' => 'license-product',
            'is_active' => true,
        ]);

        $this->sku = ProductSku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-LOOP-001',
            'name' => 'Standard License',
            'price' => 99.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $role = Role::findOrCreate('super-admin', 'web');
        \DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $admin->id],
            ['tenant_id' => $this->tenant->id]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->userToken = $this->user->createToken('buyer', ['*'])->plainTextToken;
        $this->adminToken = $admin->createToken('admin', ['*'])->plainTextToken;
    }

    private function userHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->userToken];
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    /** @test */
    public function cart_checkout_deducts_stock_once(): void
    {
        $this->postJson('/api/cart/add', [
            'sku_id' => $this->sku->id,
            'quantity' => 2,
        ], $this->userHeaders())->assertOk();

        $checkout = $this->postJson('/api/cart/checkout', [], $this->userHeaders());
        $checkout->assertStatus(201)->assertJsonPath('success', true);

        $this->assertSame(8, $this->sku->fresh()->stock);
    }

    /** @test */
    public function full_cart_pay_and_refund_loop_works_with_mock_payment(): void
    {
        $this->postJson('/api/cart/add', [
            'sku_id' => $this->sku->id,
            'quantity' => 1,
        ], $this->userHeaders())->assertOk();

        $checkout = $this->postJson('/api/cart/checkout', [], $this->userHeaders());
        $checkout->assertStatus(201);
        $orderId = $checkout->json('data.id');
        $this->assertNotNull($orderId);
        $this->assertSame(9, $this->sku->fresh()->stock);

        $pay = $this->postJson("/api/orders/{$orderId}/pay", ['gateway' => 'mock'], $this->userHeaders());
        $pay->assertOk()->assertJsonPath('success', true);

        $order = Order::findOrFail($orderId);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);

        $invoice = Invoice::where('metadata->order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);

        $refundRequest = $this->postJson('/api/refunds', [
            'order_id' => $orderId,
            'reason' => '误购申请退款',
        ], $this->userHeaders());

        $refundRequest->assertStatus(201)->assertJsonPath('success', true);
        $refundId = $refundRequest->json('data.id');
        $this->assertSame(Order::STATUS_REFUNDING, $order->fresh()->status);

        $review = $this->postJson("/api/ecommerce/refunds/{$refundId}/review", [
            'action' => 'approve',
            'notes' => '同意退款',
        ], $this->adminHeaders());

        $review->assertOk()->assertJsonPath('success', true);

        $refund = Refund::findOrFail($refundId);
        $this->assertSame('completed', $refund->status);
        $this->assertNotNull($refund->payment_refund_id);
        $this->assertSame(Order::STATUS_REFUNDED, $order->fresh()->status);
        $this->assertSame(10, $this->sku->fresh()->stock);
    }

    /** @test */
    public function paid_order_cannot_be_cancelled(): void
    {
        $order = Order::create([
            'order_no' => 'HWT' . now()->format('Ymd') . 'CANCEL01',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 99,
            'discount_amount' => 0,
            'final_amount' => 99,
            'currency' => 'CNY',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->postJson("/api/orders/{$order->id}/cancel", [], $this->userHeaders())
            ->assertStatus(400);
    }

    /** @test */
    public function rejected_refund_restores_order_to_paid(): void
    {
        $order = Order::create([
            'order_no' => 'HWT' . now()->format('Ymd') . 'REJECT01',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 99,
            'discount_amount' => 0,
            'final_amount' => 99,
            'currency' => 'CNY',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'payment_method' => 'mock',
        ]);

        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_no' => 'INV-' . $order->order_no,
            'amount' => 99,
            'status' => 'paid',
            'metadata' => ['order_id' => $order->id],
        ]);

        $refund = Refund::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'refund_no' => 'RFTEST0001',
            'amount' => 99,
            'currency' => 'CNY',
            'reason' => '测试',
            'status' => 'pending',
            'refund_type' => 'full',
            'customer_requested_at' => now(),
        ]);
        $order->update(['status' => Order::STATUS_REFUNDING]);

        $this->postJson("/api/ecommerce/refunds/{$refund->id}/review", [
            'action' => 'reject',
            'reason' => '不符合退款条件',
        ], $this->adminHeaders())->assertOk();

        $this->assertSame('rejected', $refund->fresh()->status);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }
}
