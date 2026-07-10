<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\TicketReply;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Ticket $ticket;
    private TicketCategory $category;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->category = TicketCategory::create([
            'name' => '技术支持',
            'description' => '技术相关问题',
        ]);

        $this->ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 工单 CRUD ───

    public function test_index_returns_paginated_tickets(): void
    {
        Ticket::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/tickets?per_page=3', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Laravel paginate() returns flat metadata alongside data[] in JSON
        $responseData = $response->json('data');
        $this->assertArrayHasKey('current_page', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertIsArray($responseData['data']);
    }

    public function test_index_filters_by_status(): void
    {
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'closed',
        ]);

        $response = $this->getJson('/api/tickets?status=closed', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_store_creates_ticket(): void
    {
        $response = $this->postJson('/api/tickets', [
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => '测试工单',
            'description' => '这是一个测试工单的详细描述，满足至少20个字符的要求',
            'priority' => 'medium',
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', '测试工单');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/tickets', [], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_ticket_detail(): void
    {
        $response = $this->getJson("/api/tickets/{$this->ticket->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->ticket->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/tickets/99999', $this->authHeaders());

        $response->assertStatus(404);
    }

    // ─── 工单操作 ───

    public function test_assign_ticket(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson(
            "/api/tickets/{$this->ticket->id}/assign",
            ['user_id' => $admin->id],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reply_to_ticket(): void
    {
        $response = $this->postJson(
            "/api/tickets/{$this->ticket->id}/reply",
            ['content' => '这是回复内容'],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $this->ticket->id,
            'content' => '这是回复内容',
        ]);
    }

    public function test_close_ticket(): void
    {
        $response = $this->postJson(
            "/api/tickets/{$this->ticket->id}/close",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_resolve_ticket(): void
    {
        $response = $this->postJson(
            "/api/tickets/{$this->ticket->id}/resolve",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'status' => 'resolved',
        ]);
    }

    public function test_reopen_ticket(): void
    {
        // 先关闭
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->postJson(
            "/api/tickets/{$this->ticket->id}/reopen",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'status' => 'open',
        ]);
    }

    public function test_satisfaction_rating(): void
    {
        // 先关闭工单
        $this->ticket->update(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->postJson(
            "/api/tickets/{$this->ticket->id}/satisfaction",
            ['score' => 5, 'comment' => '非常满意'],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ─── 统计 ───

    public function test_stats(): void
    {
        Ticket::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'priority' => 'high',
        ]);

        $response = $this->getJson('/api/tickets/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    // ─── 权限 ───

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/tickets');
        $response->assertStatus(401);
    }
}
