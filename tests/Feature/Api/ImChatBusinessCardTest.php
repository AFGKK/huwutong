<?php

namespace Tests\Feature\Api;

use App\Models\ConversationParticipant;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatBusinessCardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $buyer;
    private User $seller;
    private User $stranger;
    private string $tokenBuyer;
    private string $tokenSeller;
    private string $tokenStranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->buyer = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Buyer']);
        $this->seller = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Seller']);
        $this->stranger = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Stranger']);
        $this->tokenBuyer = $this->buyer->createToken('test-token', ['*'])->plainTextToken;
        $this->tokenSeller = $this->seller->createToken('test-token', ['*'])->plainTextToken;
        $this->tokenStranger = $this->stranger->createToken('test-token', ['*'])->plainTextToken;

        UserPrivacySetting::defaultFor($this->buyer->id)->update(['dm_policy' => 'everyone']);
        UserPrivacySetting::defaultFor($this->seller->id)->update(['dm_policy' => 'everyone']);
        UserPrivacySetting::defaultFor($this->stranger->id)->update(['dm_policy' => 'everyone']);
    }

    protected function headers(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function makeProduct(): Product
    {
        return Product::factory()->create([
            'user_id' => $this->seller->id,
            'name' => 'Card Product',
            'is_active' => true,
        ]);
    }

    private function makeOrder(Product $product, User $buyer): Order
    {
        $order = Order::create([
            'order_no' => 'ORD-CARD-' . uniqid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $buyer->id,
            'total_amount' => 199.00,
            'discount_amount' => 0,
            'final_amount' => 199.00,
            'currency' => 'CNY',
            'status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 199.00,
            'quantity' => 1,
            'subtotal' => 199.00,
        ]);

        return $order->fresh('items.product');
    }

    private function makeConversation(User $a, User $b): UserConversation
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $a->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $a->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $b->id, 'role' => 'member']);

        return $conv;
    }

    public function test_buyer_can_send_order_card_by_order_id(): void
    {
        Event::fake();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, $this->buyer);
        $conv = $this->makeConversation($this->buyer, $this->seller);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/send-order-card", [
            'order_id' => $order->id,
        ], $this->headers($this->tokenBuyer));

        $r->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conv->id,
            'sender_id' => $this->buyer->id,
            'message_type' => 'card',
        ]);
        $this->assertSame('order_card', $r->json('data.metadata.type'));
        $this->assertSame($order->order_no, $r->json('data.metadata.order.order_number'));
    }

    public function test_agent_can_send_order_card_by_order_no(): void
    {
        Event::fake();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, $this->buyer);
        $conv = $this->makeConversation($this->seller, $this->buyer);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/send-order-card", [
            'order_no' => $order->order_no,
        ], $this->headers($this->tokenSeller));

        $r->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame('order_card', $r->json('data.metadata.type'));
    }

    public function test_stranger_cannot_send_order_card(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, $this->buyer);
        $conv = $this->makeConversation($this->stranger, $this->seller);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/send-order-card", [
            'order_id' => $order->id,
        ], $this->headers($this->tokenStranger));

        $this->assertContains($r->status(), [403, 400]);
    }

    public function test_non_participant_cannot_send_order_card(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, $this->buyer);
        $conv = $this->makeConversation($this->buyer, $this->seller);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/send-order-card", [
            'order_id' => $order->id,
        ], $this->headers($this->tokenStranger));

        $this->assertContains($r->status(), [403, 400]);
    }

    public function test_order_inquiry_opens_dm_with_order_card(): void
    {
        Event::fake();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, $this->buyer);

        $r = $this->postJson('/api/user-chat/order-inquiry', [
            'order_id' => $order->id,
            'message' => '发货了吗？',
        ], $this->headers($this->tokenBuyer));

        $r->assertStatus(201)->assertJsonPath('success', true);
        $convId = $r->json('data.conversation.id');
        $this->assertNotNull($convId);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $convId,
            'sender_id' => $this->buyer->id,
            'message_type' => 'card',
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $convId,
            'sender_id' => $this->buyer->id,
            'content' => '发货了吗？',
        ]);
    }

    public function test_ticket_owner_can_send_aftersale_card(): void
    {
        Event::fake();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $this->buyer->id,
            'subject' => '退款咨询',
            'status' => 'open',
        ]);
        $conv = $this->makeConversation($this->buyer, $this->seller);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/send-aftersale-card", [
            'ticket_id' => $ticket->id,
        ], $this->headers($this->tokenBuyer));

        $r->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame('aftersale_card', $r->json('data.metadata.type'));
        $this->assertSame('退款咨询', $r->json('data.metadata.aftersale.subject'));
    }

    public function test_stranger_cannot_send_aftersale_card(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $this->buyer->id,
            'subject' => '别人的工单',
        ]);
        $conv = $this->makeConversation($this->stranger, $this->seller);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/send-aftersale-card", [
            'ticket_id' => $ticket->id,
        ], $this->headers($this->tokenStranger));

        $this->assertContains($r->status(), [403, 400]);
    }

    public function test_ticket_inquiry_opens_dm_with_aftersale_card(): void
    {
        Event::fake();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $this->buyer->id,
            'assigned_to' => $this->seller->id,
            'subject' => '退款进度',
            'status' => 'open',
        ]);

        $r = $this->postJson('/api/user-chat/ticket-inquiry', [
            'ticket_id' => $ticket->id,
            'message' => '什么时候处理？',
        ], $this->headers($this->tokenBuyer));

        $r->assertStatus(201)->assertJsonPath('success', true);
        $convId = $r->json('data.conversation.id');
        $this->assertNotNull($convId);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $convId,
            'sender_id' => $this->buyer->id,
            'message_type' => 'card',
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $convId,
            'sender_id' => $this->buyer->id,
            'content' => '什么时候处理？',
        ]);
    }

    public function test_unassigned_ticket_inquiry_is_rejected(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $this->buyer->id,
            'assigned_to' => null,
            'subject' => '未分配',
        ]);

        $r = $this->postJson('/api/user-chat/ticket-inquiry', [
            'ticket_id' => $ticket->id,
        ], $this->headers($this->tokenBuyer));

        $this->assertContains($r->status(), [400, 422]);
    }
}
