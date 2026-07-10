<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistGroup;
use App\Models\WishlistItem;
use App\Services\WishlistService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class WishlistServiceTest extends TestCase
{
    use RefreshDatabase;

    private WishlistService $service;
    private User $user;
    private Product $product1;
    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WishlistService::class);
        $this->user = User::factory()->create();
        $this->product1 = Product::create(['name' => 'Product A', 'slug' => 'product-a']);
        $this->product2 = Product::create(['name' => 'Product B', 'slug' => 'product-b']);
    }

    public function test_can_create_group()
    {
        $group = $this->service->createGroup($this->user->id, '待购清单');

        $this->assertNotNull($group->id);
        $this->assertEquals('待购清单', $group->name);
        $this->assertEquals($this->user->id, $group->user_id);
    }

    public function test_can_update_group()
    {
        $group = $this->service->createGroup($this->user->id, 'Old');
        $updated = $this->service->updateGroup($group->id, ['name' => 'New']);
        $this->assertEquals('New', $updated->name);
    }

    public function test_can_delete_group_removes_items()
    {
        $group = $this->service->createGroup($this->user->id, 'Test');
        $this->service->addItem($this->user->id, $this->product1->id, $group->id);

        $this->assertCount(1, WishlistItem::where('group_id', $group->id)->get());

        $this->service->deleteGroup($group->id);

        $this->assertNull(WishlistGroup::find($group->id));
        $this->assertCount(0, WishlistItem::where('group_id', $group->id)->get());
    }

    public function test_can_add_item_to_default_group()
    {
        $item = $this->service->addItem($this->user->id, $this->product1->id);

        $this->assertNotNull($item->id);
        $this->assertEquals($this->user->id, $item->user_id);
        $this->assertEquals($this->product1->id, $item->product_id);

        // Should create default group
        $this->assertNotNull($item->group_id);
        $group = WishlistGroup::find($item->group_id);
        $this->assertEquals('默认收藏', $group->name);
    }

    public function test_can_add_item_to_specific_group()
    {
        $group = $this->service->createGroup($this->user->id, '待购');
        $item = $this->service->addItem($this->user->id, $this->product1->id, $group->id, [
            'note' => '需要这个',
            'priority' => 2,
            'target_price' => 99.99,
            'notify_on_sale' => true,
        ]);

        $this->assertEquals($group->id, $item->group_id);
        $this->assertEquals('需要这个', $item->note);
        $this->assertEquals(2, $item->priority);
        $this->assertEquals(99.99, (float) $item->target_price);
        $this->assertTrue($item->notify_on_sale);
    }

    public function test_duplicate_item_not_created()
    {
        $group = $this->service->createGroup($this->user->id, 'Test');
        $this->service->addItem($this->user->id, $this->product1->id, $group->id);
        $this->service->addItem($this->user->id, $this->product1->id, $group->id);

        $items = WishlistItem::where('user_id', $this->user->id)
            ->where('product_id', $this->product1->id)
            ->get();

        $this->assertCount(1, $items);
    }

    public function test_can_update_item()
    {
        $item = $this->service->addItem($this->user->id, $this->product1->id);
        $updated = $this->service->updateItem($item->id, [
            'note' => 'Updated',
            'priority' => 1,
        ]);

        $this->assertEquals('Updated', $updated->note);
        $this->assertEquals(1, $updated->priority);
    }

    public function test_can_move_item()
    {
        $group1 = $this->service->createGroup($this->user->id, 'Group A');
        $group2 = $this->service->createGroup($this->user->id, 'Group B');
        $item = $this->service->addItem($this->user->id, $this->product1->id, $group1->id);

        $moved = $this->service->moveItem($item->id, $group2->id);
        $this->assertEquals($group2->id, $moved->group_id);
    }

    public function test_can_remove_item()
    {
        $item = $this->service->addItem($this->user->id, $this->product1->id);
        $this->assertNotNull(WishlistItem::find($item->id));

        $this->service->removeItem($item->id);
        $this->assertNull(WishlistItem::find($item->id));
    }

    public function test_can_batch_remove_items()
    {
        $item1 = $this->service->addItem($this->user->id, $this->product1->id);
        $item2 = $this->service->addItem($this->user->id, $this->product2->id);

        $this->service->batchRemoveItems([$item1->id, $item2->id]);

        $this->assertCount(0, WishlistItem::where('user_id', $this->user->id)->get());
    }

    public function test_toggle_adds_and_removes()
    {
        // First toggle adds
        $item = $this->service->toggleItem($this->user->id, $this->product1->id);
        $this->assertNotNull($item);
        $this->assertTrue($this->service->isWishlisted($this->user->id, $this->product1->id));

        // Second toggle removes
        $result = $this->service->toggleItem($this->user->id, $this->product1->id);
        $this->assertNull($result);
        $this->assertFalse($this->service->isWishlisted($this->user->id, $this->product1->id));
    }

    public function test_get_user_stats()
    {
        $this->service->addItem($this->user->id, $this->product1->id, null, [
            'notify_on_sale' => true,
            'priority' => 2,
        ]);
        $this->service->addItem($this->user->id, $this->product2->id);

        $stats = $this->service->getUserStats($this->user->id);

        $this->assertEquals(2, $stats['total_items']);
        $this->assertEquals(1, $stats['total_groups']); // default group
        $this->assertEquals(1, $stats['sale_notify']);
        $this->assertEquals(1, $stats['high_priority']);
    }

    public function test_get_wishlisted_product_ids()
    {
        $this->service->addItem($this->user->id, $this->product1->id);
        $this->service->addItem($this->user->id, $this->product2->id);

        $ids = $this->service->getUserWishlistedProductIds($this->user->id);

        $this->assertCount(2, $ids);
        $this->assertContains($this->product1->id, $ids);
        $this->assertContains($this->product2->id, $ids);
    }

    public function test_can_create_share_link()
    {
        $group = $this->service->createGroup($this->user->id, 'Shareable');
        $share = $this->service->createShareLink($group->id, $this->user->id);

        $this->assertNotNull($share->id);
        $this->assertEquals(32, strlen($share->share_token));
        $this->assertEquals($group->id, $share->wishlist_group_id);
    }

    public function test_can_get_shared_by_token()
    {
        $group = $this->service->createGroup($this->user->id, 'Shared');
        $this->service->addItem($this->user->id, $this->product1->id, $group->id);
        $share = $this->service->createShareLink($group->id, $this->user->id);

        $loaded = $this->service->getSharedByToken($share->share_token);
        $this->assertNotNull($loaded);
        $this->assertEquals($share->id, $loaded->id);
    }

    public function test_shared_link_expires()
    {
        $group = $this->service->createGroup($this->user->id, 'Expired');
        $share = $this->service->createShareLink($group->id, $this->user->id, [
            'expires_at' => now()->subDay(),
        ]);

        $loaded = $this->service->getSharedByToken($share->share_token);
        $this->assertNull($loaded);
    }

    public function test_can_delete_share()
    {
        $group = $this->service->createGroup($this->user->id, 'Test');
        $share = $this->service->createShareLink($group->id, $this->user->id);

        $this->service->deleteShare($share->id);
        $this->assertNull($share->fresh());
    }

    public function test_get_user_wishlists_returns_groups_with_items()
    {
        $g1 = $this->service->createGroup($this->user->id, 'Group 1');
        $this->service->addItem($this->user->id, $this->product1->id, $g1->id);

        $g2 = $this->service->createGroup($this->user->id, 'Group 2');
        $this->service->addItem($this->user->id, $this->product2->id, $g2->id);

        $wishlists = $this->service->getUserWishlists($this->user->id);

        $this->assertCount(2, $wishlists);
        $this->assertCount(1, $wishlists->first()->items);
    }
}
