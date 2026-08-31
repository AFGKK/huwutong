<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PreSaleCampaign;
use App\Services\PreSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreSaleController extends Controller
{
    public function __construct(
        protected PreSaleService $preSaleService,
    ) {}

    // ─── 活动管理 ───

    public function index(Request $request)
    {
        return ApiResponse::success($this->preSaleService->listCampaigns($request->only([
            'type', 'status', 'tenant_id', 'search', 'sort', 'per_page',
        ])));
    }

    public function show(int $id)
    {
        $campaign = \App\Models\PreSaleCampaign::with([
            'product:id,name,slug,description,is_active',
            'updates' => fn($q) => $q->latest(),
        ])->withCount('orders')->findOrFail($id);

        return ApiResponse::success($campaign);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'type' => 'required|string|in:pre_sale,crowdfunding',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
            'product_id' => 'required|exists:products,id',
            'target_amount' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'deposit_rate' => 'nullable|numeric|min:0|max:100',
            'deposit_amount' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'estimated_delivery_at' => 'nullable|date|after:start_at',
            'target_backers' => 'nullable|integer|min:1',
            'tiers' => 'nullable|array',
            'tiers.*.name' => 'required|string|max:200',
            'tiers.*.amount' => 'required|numeric|min:0',
            'tiers.*.description' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $campaign = $this->preSaleService->createCampaign($validator->validated());
        return ApiResponse::success($campaign, 201, __('app.api.pre_sale.campaign_created'));
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'target_amount' => 'nullable|numeric|min:0',
            'deposit_rate' => 'nullable|numeric|min:0|max:100',
            'deposit_amount' => 'nullable|numeric|min:0',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
            'estimated_delivery_at' => 'nullable|date|after:start_at',
            'target_backers' => 'nullable|integer|min:1',
            'tiers' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $campaign = $this->preSaleService->updateCampaign($id, $validator->validated());
        return ApiResponse::success($campaign, 200, __('app.api.pre_sale.updated'));
    }

    public function publish(int $id)
    {
        try {
            $campaign = $this->preSaleService->publishCampaign($id);
            return ApiResponse::success($campaign, 200, __('app.api.pre_sale.campaign_published'));
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request, int $id)
    {
        try {
            $campaign = $this->preSaleService->cancelCampaign($id, $request->input('reason'));
            return ApiResponse::success($campaign, 200, __('app.api.pre_sale.campaign_cancelled'));
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    public function checkStatus(int $id)
    {
        $campaign = $this->preSaleService->checkCampaignStatus($id);
        return ApiResponse::success($campaign);
    }

    public function complete(int $id)
    {
        try {
            $campaign = $this->preSaleService->completeCampaign($id);
            return ApiResponse::success($campaign, 200, __('app.api.pre_sale.campaign_completed'));
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    public function stats()
    {
        return ApiResponse::success($this->preSaleService->getStats());
    }

    public function destroy(int $id)
    {
        try {
            $this->preSaleService->deleteCampaign($id);
            return ApiResponse::success(['message' => __('app.api.pre_sale.deleted')]);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    // ─── 订单管理 ───

    public function orders(Request $request)
    {
        return ApiResponse::success($this->preSaleService->listOrders($request->only([
            'campaign_id', 'payment_status', 'user_id',
        ])));
    }

    public function placeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:pre_sale_campaigns,id',
            'customer_id' => 'nullable|exists:customers,id',
            'tier_index' => 'nullable|integer|min:0',
            'tier_name' => 'nullable|string|max:200',
            'quantity' => 'integer|min:1|max:999',
            'total_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;

        try {
            $order = $this->preSaleService->placeOrder($data);
            return ApiResponse::success($order, 201, __('app.api.pre_sale.order_placed'));
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    public function payDeposit(Request $request, int $orderId)
    {
        try {
            $order = $this->preSaleService->payDeposit($orderId, $request->input('payment_method'));
            return ApiResponse::success($order, 200, __('app.api.pre_sale.deposit_paid'));
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    public function payFinal(Request $request, int $orderId)
    {
        try {
            $order = $this->preSaleService->payFinal($orderId, $request->input('payment_method'));
            return ApiResponse::success($order, 200, __('app.api.pre_sale.final_paid'));
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 400);
        }
    }

    public function updateFulfillment(Request $request, int $orderId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,processing,shipped,delivered',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $order = $this->preSaleService->updateFulfillmentStatus($orderId, $request->input('status'));
        return ApiResponse::success($order, 200, __('app.api.pre_sale.fulfillment_updated'));
    }

    // ─── 活动更新 ───

    public function postUpdate(Request $request, int $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'type' => 'string|in:update,milestone,announcement',
            'is_pinned' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $update = $this->preSaleService->postUpdate($campaignId, $validator->validated());
        return ApiResponse::success($update, 201, __('app.api.pre_sale.update_published'));
    }

    public function updates(int $campaignId)
    {
        return ApiResponse::success($this->preSaleService->getUpdates($campaignId));
    }

    public function deleteUpdate(int $updateId)
    {
        $this->preSaleService->deleteUpdate($updateId);
        return ApiResponse::success(['message' => __('app.api.pre_sale.update_deleted')]);
    }

    // ─── 公开API ───

    public function published(Request $request)
    {
        return ApiResponse::success($this->preSaleService->listPublishedCampaigns($request->only(['type'])));
    }

    public function publicShow(string $slug)
    {
        $campaign = PreSaleCampaign::with([
            'product:id,name,slug,description',
            'updates' => fn($q) => $q->latest()->take(5),
        ])->withCount('orders')->where('slug', $slug)->firstOrFail();

        return ApiResponse::success($campaign);
    }
}
