<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductReviewController extends Controller
{
    public function __construct(
        protected ProductReviewService $reviewService,
    ) {}

    // ─── 公开API（无需认证） ───

    /**
     * 获取商品评论列表
     */
    public function productReviews(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);

        $reviews = $this->reviewService->getProductReviews($productId, $request->only([
            'rating', 'tag', 'sort', 'per_page',
        ]));

        return ApiResponse::success($reviews);
    }

    /**
     * 获取商品评分统计
     */
    public function productRatingStats(int $productId)
    {
        $product = Product::findOrFail($productId);
        return ApiResponse::success($this->reviewService->getProductRatingStats($productId));
    }

    /**
     * 创建评论（需要认证+已购验证）
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string|min:5|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_anonymous' => 'boolean',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;

        // 如果没有传 customer_id，尝试从 user 获取
        if (empty($data['customer_id'])) {
            $customer = $request->user()->customer;
            $data['customer_id'] = $customer?->id;
        }

        $review = $this->reviewService->createReview($data);

        return ApiResponse::success($review, '评论已提交，等待审核');
    }

    // ─── 管理端API ───

    /**
     * 管理端评论列表
     */
    public function index(Request $request)
    {
        $reviews = $this->reviewService->listReviews($request->only([
            'status', 'product_id', 'rating', 'search', 'sort', 'per_page',
        ]));

        return ApiResponse::success($reviews);
    }

    /**
     * 评论详情
     */
    public function show(int $id)
    {
        $review = \App\Models\ProductReview::with([
            'user:id,name,email,avatar',
            'product:id,name,slug',
            'customer:id',
            'replier:id,name',
        ])->findOrFail($id);

        return ApiResponse::success($review);
    }

    /**
     * 审核评论
     */
    public function moderate(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected',
            'reject_reason' => 'required_if:status,rejected|string|max:200',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $review = $this->reviewService->moderateReview(
            $id,
            $request->input('status'),
            $request->input('reject_reason'),
            $request->user()->id,
        );

        $message = $request->input('status') === 'approved' ? '评论已通过' : '评论已驳回';
        return ApiResponse::success($review, $message);
    }

    /**
     * 商家回复
     */
    public function reply(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_reply' => 'required|string|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $review = $this->reviewService->replyToReview(
            $id,
            $request->input('admin_reply'),
            $request->user()->id,
        );

        return ApiResponse::success($review, '回复已提交');
    }

    /**
     * 删除评论
     */
    public function destroy(int $id)
    {
        $this->reviewService->deleteReview($id);
        return ApiResponse::success(['message' => '评论已删除']);
    }

    /**
     * 评论统计
     */
    public function stats()
    {
        return ApiResponse::success($this->reviewService->getStats());
    }
}
