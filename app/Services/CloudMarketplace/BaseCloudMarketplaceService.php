<?php

namespace App\Services\CloudMarketplace;

use App\Models\CloudMarketplaceProduct;
use App\Models\CloudMarketplaceSubscription;
use App\Models\CloudMarketplaceMetering;
use Illuminate\Support\Facades\Log;

/**
 * 云市场集成 — 基类
 * 
 * 统一 AWS Marketplace 的集成逻辑。
 * 每个云市场子类需要实现:
 *  - resolveSubscription(): 解析三方订阅信息
 *  - activateSubscription(): 激活订阅
 *  - deactivateSubscription(): 停用订阅
 *  - reportMetering(): 上报计量数据
 *  - getEntitlement(): 查询订阅权益
 *  - handleNotification(): 处理三方推送通知
 */
abstract class BaseCloudMarketplaceService
{
    protected string $marketplace;
    
    /**
     * 获取配置
     */
    abstract protected function config(string $key, mixed $default = null): mixed;

    /**
     * 解析三方订阅标识 -> 本地订阅数据
     * @param array $params 三方传入的参数（不同平台不同）
     * @return array ['subscription_id' => '...', 'customer_id' => '...', 'customer_email' => '...', ...]
     */
    abstract public function resolveSubscription(array $params): array;

    /**
     * 激活三方订阅 -> 创建本地订阅
     */
    abstract public function activateSubscription(CloudMarketplaceSubscription $subscription, array $params): bool;

    /**
     * 停用/取消三方订阅
     */
    abstract public function deactivateSubscription(CloudMarketplaceSubscription $subscription): bool;

    /**
     * 上报计量数据到云市场
     * @param CloudMarketplaceMetering[] $records
     */
    abstract public function reportMetering(array $records): bool;

    /**
     * 查询订阅权益（客户当前的有效订阅状态）
     */
    abstract public function getEntitlement(CloudMarketplaceSubscription $subscription): array;

    /**
     * 处理云市场推送通知（SNS/PubSub/Webhook）
     */
    abstract public function handleNotification(array $payload): array;

    /**
     * 根据 offer_id 查找本地产品映射
     */
    protected function findProduct(string $offerId): ?CloudMarketplaceProduct
    {
        return CloudMarketplaceProduct::where('marketplace', $this->marketplace)
            ->where('offer_id', $offerId)
            ->first();
    }

    /**
     * 根据三方订阅ID查找本地订阅记录
     */
    protected function findSubscription(string $marketplaceSubscriptionId): ?CloudMarketplaceSubscription
    {
        return CloudMarketplaceSubscription::where('marketplace', $this->marketplace)
            ->where('marketplace_subscription_id', $marketplaceSubscriptionId)
            ->first();
    }

    /**
     * 创建或更新订阅记录
     */
    protected function upsertSubscription(array $data): CloudMarketplaceSubscription
    {
        return CloudMarketplaceSubscription::updateOrCreate(
            [
                'marketplace' => $this->marketplace,
                'marketplace_subscription_id' => $data['marketplace_subscription_id'],
            ],
            $data
        );
    }

    /**
     * 记录通知日志
     */
    protected function logNotification(string $type, array $payload, string $status = 'received', ?string $error = null, ?int $tenantId = null): void
    {
        try {
            \App\Models\CloudMarketplaceNotification::create([
                'tenant_id' => $tenantId ?? 1, // 公开通知默认使用主租户
                'marketplace' => $this->marketplace,
                'notification_type' => $type,
                'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'error_message' => $error,
                'processed_at' => $status === 'processed' ? now() : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log marketplace notification', [
                'marketplace' => $this->marketplace,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * HTTP 客户端（Guzzle）
     */
    protected function httpClient(array $options = [])
    {
        return new \GuzzleHttp\Client(array_merge([
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
        ], $options));
    }
}
