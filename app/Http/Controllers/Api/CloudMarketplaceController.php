<?php

namespace App\Http\Controllers\Api;

use App\Models\CloudMarketplaceProduct;
use App\Models\CloudMarketplaceSubscription;
use App\Models\CloudMarketplaceMetering;
use App\Services\CloudMarketplace\CloudMarketplaceServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;

/**
 * 云市场集成控制器
 * 
 * 统一管理 AWS Marketplace 的对接
 */
class CloudMarketplaceController extends Controller
{
    /**
     * 获取云市场配置状态
     */
    public function status()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'marketplaces' => CloudMarketplaceServiceFactory::marketplaceList(),
                'total_products' => CloudMarketplaceProduct::count(),
                'total_subscriptions' => CloudMarketplaceSubscription::count(),
                'active_subscriptions' => CloudMarketplaceSubscription::whereIn('status', ['subscribed', 'active'])->count(),
                'pending_metering' => CloudMarketplaceMetering::where('status', 'pending')->count(),
            ],
        ]);
    }

    // ─── 产品/Offer 管理 ───

    /**
     * 产品映射列表
     */
    public function products(Request $request)
    {
        $query = CloudMarketplaceProduct::query();

        if ($request->marketplace) {
            $query->where('marketplace', $request->marketplace);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('marketplace')->orderBy('offer_name')->paginate(20),
        ]);
    }

    /**
     * 创建产品映射
     */
    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'marketplace' => 'required|in:aws',
            'offer_id' => 'required|string|max:255',
            'offer_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'mapping_rules' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,deprecated',
        ]);

        $product = CloudMarketplaceProduct::create(array_merge(
            $validated,
            ['tenant_id' => $request->user()->tenant_id ?? 1]
        ));

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    /**
     * 更新产品映射
     */
    public function updateProduct(Request $request, CloudMarketplaceProduct $product)
    {
        $validated = $request->validate([
            'offer_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'mapping_rules' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,deprecated',
        ]);

        $product->update($validated);
        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * 删除产品映射
     */
    public function destroyProduct(CloudMarketplaceProduct $product)
    {
        $product->delete();
        return response()->json(['success' => true]);
    }

    // ─── 订阅管理 ───

    /**
     * 订阅列表
     */
    public function subscriptions(Request $request)
    {
        $query = CloudMarketplaceSubscription::with(['localCustomer', 'localSubscription']);

        if ($request->marketplace) {
            $query->where('marketplace', $request->marketplace);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }

    /**
     * 订阅详情
     */
    public function showSubscription(CloudMarketplaceSubscription $subscription)
    {
        $subscription->load(['localCustomer', 'localUser', 'localSubscription', 'metering' => function ($q) {
            $q->latest()->limit(20);
        }]);

        return response()->json(['success' => true, 'data' => $subscription]);
    }

    // ─── 计量记录 ───

    /**
     * 计量记录列表
     */
    public function metering(Request $request)
    {
        $query = CloudMarketplaceMetering::with('subscription');

        if ($request->subscription_id) {
            $query->where('subscription_id', $request->subscription_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->marketplace) {
            $query->where('marketplace', $request->marketplace);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('metered_at')->paginate(20),
        ]);
    }

    /**
     * 手动上报计量
     */
    public function reportMetering(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:cloud_marketplace_subscriptions,id',
            'dimension' => 'required|string|max:100',
            'quantity' => 'required|numeric|min:0',
            'metered_at' => 'nullable|date',
        ]);

        $subscription = CloudMarketplaceSubscription::findOrFail($validated['subscription_id']);

        $record = CloudMarketplaceMetering::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'marketplace' => $subscription->marketplace,
            'dimension' => $validated['dimension'],
            'quantity' => $validated['quantity'],
            'metered_at' => $validated['metered_at'] ?? now(),
        ]);

        // 尝试立即上报
        try {
            $service = CloudMarketplaceServiceFactory::make($subscription->marketplace);
            $service->reportMetering([$record]);
        } catch (\Exception $e) {
            Log::warning('Manual metering report deferred', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $record], 201);
    }

    // ─── 三方通知处理（公开端点） ───

    /**
     * AWS SNS 通知入口
     */
    public function awsSnsNotification(Request $request)
    {
        $payload = $request->all();

        // AWS SNS 订阅确认
        if (isset($payload['Type']) && $payload['Type'] === 'SubscriptionConfirmation') {
            $this->confirmAwsSnsSubscription($payload);
            return response('OK', 200);
        }

        try {
            $service = CloudMarketplaceServiceFactory::make('aws');
            $result = $service->handleNotification($payload);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('AWS SNS handler failed', ['error' => $e->getMessage()]);
            return response('Error', 500);
        }
    }

    /**
     * 确认 AWS SNS 订阅（自动确认）
     */
    protected function confirmAwsSnsSubscription(array $payload)
    {
        $subscribeUrl = $payload['SubscribeURL'] ?? '';
        if ($subscribeUrl) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 10]);
                $client->get($subscribeUrl);
                Log::info('AWS SNS subscription confirmed', ['url' => $subscribeUrl]);
            } catch (\Exception $e) {
                Log::error('AWS SNS subscription confirmation failed', [
                    'url' => $subscribeUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ─── 注册回调（仅 AWS）───

    /**
     * AWS Marketplace 注册返回
     */
    public function awsReturnUrl(Request $request)
    {
        $token = $request->get('x-amzn-marketplace-token');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Missing marketplace token'], 400);
        }

        try {
            $service = CloudMarketplaceServiceFactory::make('aws');
            $result = $service->resolveSubscription(['token' => $token]);

            $subscription = CloudMarketplaceSubscription::updateOrCreate(
                [
                    'marketplace' => 'aws',
                    'marketplace_subscription_id' => $result['marketplace_subscription_id'],
                ],
                array_merge($result, [
                    'tenant_id' => $request->user()->tenant_id ?? 1,
                    'status' => 'subscribed',
                    'subscribed_at' => now(),
                ])
            );

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'redirect' => '/admin/marketplace/subscriptions/' . $subscription->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve AWS subscription: ' . $e->getMessage(),
            ], 500);
        }
    }
}
