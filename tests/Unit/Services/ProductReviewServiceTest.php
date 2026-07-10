<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductReviewService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductReviewService $service;
    private Product $product;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductReviewService::class);
        $this->product = Product::create(['name' => 'Test Product', 'slug' => 'test-product']);
        $this->user = User::factory()->create();
        $this->customer = Customer::create([
            'tenant_id' => Tenant::factory()->create()->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_create_review()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'content' => 'Excellent product, highly recommended!',
            'images' => ['https://example.com/img1.jpg'],
            'tags' => ['好评'],
        ]);

        $this->assertNotNull($review->id);
        $this->assertEquals(5, $review->rating);
        $this->assertEquals('pending', $review->status);
        $this->assertEquals('Excellent product, highly recommended!', $review->content);
    }

    public function test_anonymous_review()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 4,
            'content' => 'Good product',
            'is_anonymous' => true,
        ]);

        $this->assertTrue($review->is_anonymous);
    }

    public function test_can_moderate_review_to_approved()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 3,
            'content' => 'Average product',
        ]);

        $this->assertEquals('pending', $review->status);

        $approved = $this->service->moderateReview($review->id, 'approved');
        $this->assertEquals('approved', $approved->status);
    }

    public function test_can_moderate_review_to_rejected()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 1,
            'content' => 'Not good',
        ]);

        $rejected = $this->service->moderateReview($review->id, 'rejected', 'Spam content');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Spam content', $rejected->reject_reason);
    }

    public function test_can_reply_to_review()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 4,
            'content' => 'Great product',
        ]);

        $this->service->moderateReview($review->id, 'approved');

        $replied = $this->service->replyToReview($review->id, 'Thank you for your feedback!', $this->user->id);

        $this->assertEquals('Thank you for your feedback!', $replied->admin_reply);
        $this->assertNotNull($replied->reply_at);
    }

    public function test_get_product_reviews_only_returns_approved()
    {
        // Create approved review
        $approvedReview = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'content' => 'Excellent!',
        ]);
        $this->service->moderateReview($approvedReview->id, 'approved');

        // Create pending review (should not appear)
        $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 2,
            'content' => 'Not approved yet',
        ]);

        $reviews = $this->service->getProductReviews($this->product->id);

        $this->assertEquals(1, $reviews->total());
        $this->assertEquals('Excellent!', $reviews->first()->content);
    }

    public function test_get_product_rating_stats()
    {
        // Create 3 reviews: 5* approved, 4* approved, 3* approved
        foreach ([5, 4, 3] as $rating) {
            $r = $this->service->createReview([
                'product_id' => $this->product->id,
                'user_id' => $this->user->id,
                'customer_id' => $this->customer->id,
                'rating' => $rating,
                'content' => "Review $rating stars",
            ]);
            $this->service->moderateReview($r->id, 'approved');
        }

        // Create 1 pending review
        $pendingReview = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 1,
            'content' => 'Pending review',
        ]);

        $stats = $this->service->getProductRatingStats($this->product->id);

        $this->assertEquals(3, $stats['total_reviews']); // only approved
        $this->assertEquals(4.0, $stats['avg_rating']);  // (5+4+3)/3 = 4.0
        $this->assertEquals(1, $stats['distribution']['5']);
        $this->assertEquals(1, $stats['distribution']['4']);
        $this->assertEquals(1, $stats['distribution']['3']);
        $this->assertEquals(0, $stats['distribution']['2']);
        $this->assertEquals(0, $stats['distribution']['1']);
    }

    public function test_can_filter_reviews_by_rating()
    {
        foreach ([5, 4, 5] as $rating) {
            $r = $this->service->createReview([
                'product_id' => $this->product->id,
                'user_id' => $this->user->id,
                'customer_id' => $this->customer->id,
                'rating' => $rating,
                'content' => "Rating $rating",
            ]);
            $this->service->moderateReview($r->id, 'approved');
        }

        $fiveStar = $this->service->getProductReviews($this->product->id, ['rating' => 5]);
        $this->assertEquals(2, $fiveStar->total());
    }

    public function test_can_sort_reviews()
    {
        // 3* first, then 5*
        $r1 = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 3,
            'content' => 'Average',
        ]);
        $this->service->moderateReview($r1->id, 'approved');

        $r2 = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'content' => 'Excellent',
        ]);
        $this->service->moderateReview($r2->id, 'approved');

        $highest = $this->service->getProductReviews($this->product->id, ['sort' => 'highest']);
        $this->assertEquals(5, $highest->first()->rating);
    }

    public function test_admin_list_reviews()
    {
        $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 4,
            'content' => 'Nice product',
        ]);

        $adminList = $this->service->listReviews();
        $this->assertEquals(1, $adminList->total());
    }

    public function test_can_filter_admin_list_by_status()
    {
        $r1 = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'content' => 'Great',
        ]);
        $this->service->moderateReview($r1->id, 'approved');

        $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 2,
            'content' => 'Not good',
        ]);

        $pending = $this->service->listReviews(['status' => 'pending']);
        $this->assertEquals(1, $pending->total());

        $approved = $this->service->listReviews(['status' => 'approved']);
        $this->assertEquals(1, $approved->total());
    }

    public function test_get_stats()
    {
        $r1 = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'content' => 'Great',
        ]);
        $this->service->moderateReview($r1->id, 'approved');

        $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 1,
            'content' => 'Bad',
        ]);

        $stats = $this->service->getStats();

        $this->assertEquals(2, $stats['total_reviews']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(1, $stats['approved']);
        $this->assertEquals(0, $stats['rejected']);
    }

    public function test_can_delete_review()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 3,
            'content' => 'Okay product',
        ]);

        $this->service->deleteReview($review->id);

        $this->assertNull(ProductReview::find($review->id));
    }

    public function test_invalid_moderate_status_throws_exception()
    {
        $review = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 4,
            'content' => 'Good',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->moderateReview($review->id, 'invalid_status');
    }

    public function test_review_can_be_filtered_by_tag()
    {
        $r1 = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'content' => 'Perfect',
            'tags' => ['好评'],
        ]);
        $this->service->moderateReview($r1->id, 'approved');

        $r2 = $this->service->createReview([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'rating' => 2,
            'content' => 'Not great',
            'tags' => ['差评'],
        ]);
        $this->service->moderateReview($r2->id, 'approved');

        $goodTagged = $this->service->getProductReviews($this->product->id, ['tag' => '好评']);
        $this->assertEquals(1, $goodTagged->total());
    }
}
