<?php

namespace App\Services\CloudMarketplace;

use App\Models\CloudMarketplaceSubscription;
use App\Models\CloudMarketplaceMetering;
use Illuminate\Support\Facades\Log;

/**
 * AWS Marketplace 集成服务
 * 
 * 集成点:
 * - SNS 通知（订阅/取消/续期）
 * - SaaS Subscription API
 * - Marketplace Metering Service（按量计费）
 * - Entitlement Service（权益查询）
 */
class AwsMarketplaceService extends BaseCloudMarketplaceService
{
    protected string $marketplace = 'aws';

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("cloud-marketplace.aws.{$key}", $default);
    }

    /**
     * 解析 AWS Marketplace 的注册 Token → 获取订阅信息
     * 客户从 AWS 控制台点击"继续订阅"后，跳转到我们的注册页面并携带 x-amzn-marketplace-token
     */
    public function resolveSubscription(array $params): array
    {
        $token = $params['x-amzn-marketplace-token'] ?? $params['token'] ?? '';
        if (!$token) {
            throw new \InvalidArgumentException('Missing x-amzn-marketplace-token');
        }

        // 调用 AWS Marketplace Entitlement Service 解析 token
        $entitlement = $this->callAwsApi('entitlement', [
            'Action' => 'ResolveCustomer',
            'RegistrationToken' => $token,
        ]);

        $customerIdentifier = $entitlement['CustomerIdentifier'] ?? '';
        $customerAWSAccountId = $entitlement['CustomerAWSAccountId'] ?? '';
        $productCode = $entitlement['ProductCode'] ?? '';

        return [
            'marketplace_subscription_id' => $customerIdentifier,
            'offer_id' => $productCode,
            'customer_id' => $customerAWSAccountId,
            'customer_name' => "AWS Account {$customerAWSAccountId}",
            'customer_email' => '',
            'fulfillment_data' => [
                'customer_identifier' => $customerIdentifier,
                'product_code' => $productCode,
            ],
        ];
    }

    /**
     * 激活 AWS Marketplace 订阅
     * 调用 SaaS Activate API 确认订阅生效
     */
    public function activateSubscription(CloudMarketplaceSubscription $subscription, array $params = []): bool
    {
        $data = $subscription->fulfillment_data ?? [];

        try {
            $this->callAwsApi('entitlement', [
                'Action' => 'BatchMeterUsage',  // 使用计量 API 确认激活
                'ProductCode' => $data['product_code'] ?? $subscription->offer_id,
                'CustomerIdentifier' => $data['customer_identifier'] ?? $subscription->marketplace_subscription_id,
            ]);

            $subscription->update([
                'status' => 'active',
                'activated_at' => now(),
            ]);

            $this->logNotification('subscription_activated', $data, 'processed');
            return true;
        } catch (\Exception $e) {
            Log::error('AWS Marketplace activation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 停用 AWS Marketplace 订阅
     */
    public function deactivateSubscription(CloudMarketplaceSubscription $subscription): bool
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->logNotification('subscription_cancelled', $subscription->toArray(), 'processed');
        return true;
    }

    /**
     * 上报计量数据到 AWS Marketplace Metering Service
     * 
     * AWS 要求每小时上报一次，每次最多 25 条记录
     * 维度包括：api_calls, storage_gb, users, bandwidth_gb 等
     */
    public function reportMetering(array $records): bool
    {
        if (empty($records)) return true;

        $first = $records[0];
        $subscription = $first->subscription;
        $data = $subscription->fulfillment_data ?? [];

        // 按维度分组汇总
        $dimensions = [];
        foreach ($records as $record) {
            $key = $record->dimension;
            if (!isset($dimensions[$key])) {
                $dimensions[$key] = 0;
            }
            $dimensions[$key] += (float) $record->quantity;
        }

        // 构建 AWS BatchMeterUsage 请求
        $usageRecords = [];
        foreach ($dimensions as $dimension => $quantity) {
            $usageRecords[] = [
                'Dimension' => $dimension,
                'Quantity' => (int) ceil($quantity),
                'Timestamp' => now()->toISOString(),
            ];
        }

        try {
            $result = $this->callAwsApi('metering', [
                'Action' => 'BatchMeterUsage',
                'ProductCode' => $data['product_code'] ?? $subscription->offer_id,
                'CustomerIdentifier' => $data['customer_identifier'] ?? $subscription->marketplace_subscription_id,
                'UsageRecords' => $usageRecords,
            ]);

            $batchId = $result['BatchMeterUsageResult']['BatchId'] ?? uniqid('aws_');

            foreach ($records as $record) {
                $record->update([
                    'status' => 'reported',
                    'reported_at' => now(),
                    'batch_id' => $batchId,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            foreach ($records as $record) {
                $record->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            Log::error('AWS Marketplace metering report failed', [
                'batch_size' => count($records),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 查询 AWS Marketplace 订阅权益
     */
    public function getEntitlement(CloudMarketplaceSubscription $subscription): array
    {
        $data = $subscription->fulfillment_data ?? [];

        try {
            $result = $this->callAwsApi('entitlement', [
                'Action' => 'GetEntitlements',
                'ProductCode' => $data['product_code'] ?? $subscription->offer_id,
                'Filter' => [
                    'CustomerIdentifier' => [$data['customer_identifier'] ?? $subscription->marketplace_subscription_id],
                ],
            ]);

            return $result['Entitlements'] ?? [];
        } catch (\Exception $e) {
            Log::error('AWS Marketplace entitlement check failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 处理 AWS SNS 通知
     * 
     * AWS Marketplace 通过 SNS 发送以下通知:
     * - subscribe: 客户订阅
     * - unsubscribe: 客户取消
     * - renew: 自动续期
     * - change: 套餐变更
     */
    public function handleNotification(array $payload): array
    {
        $message = json_decode($payload['Message'] ?? '{}', true);
        $notificationType = $message['action'] ?? $payload['Type'] ?? 'unknown';

        $this->logNotification($notificationType, $payload, 'received');

        try {
            switch ($notificationType) {
                case 'subscribe':
                case 'subscribe-success':
                    $subscriptionId = $message['customer-identifier'] ?? $message['CustomerIdentifier'] ?? '';
                    $productCode = $message['product-code'] ?? $message['ProductCode'] ?? '';

                    $this->upsertSubscription([
                        'tenant_id' => 1,
                        'marketplace' => 'aws',
                        'marketplace_subscription_id' => $subscriptionId,
                        'offer_id' => $productCode,
                        'status' => 'subscribed',
                        'subscribed_at' => now(),
                    ]);
                    break;

                case 'unsubscribe':
                    $subscriptionId = $message['customer-identifier'] ?? $message['CustomerIdentifier'] ?? '';
                    $sub = $this->findSubscription($subscriptionId);
                    if ($sub) {
                        $this->deactivateSubscription($sub);
                    }
                    break;

                case 'renew':
                    $subscriptionId = $message['customer-identifier'] ?? $message['CustomerIdentifier'] ?? '';
                    $sub = $this->findSubscription($subscriptionId);
                    if ($sub) {
                        $sub->update(['status' => 'active']);
                    }
                    break;
            }

            $this->logNotification($notificationType, $payload, 'processed');
            return ['status' => 'processed', 'type' => $notificationType];
        } catch (\Exception $e) {
            $this->logNotification($notificationType, $payload, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * 调用 AWS Marketplace API（简化版签名）
     * 
     * 生产环境建议使用 AWS SDK for PHP (aws/aws-sdk-php)
     */
    protected function callAwsApi(string $service, array $params): array
    {
        $endpoint = $service === 'metering'
            ? $this->config('metering_endpoint')
            : $this->config('entitlement_endpoint');

        $accessKey = $this->config('access_key_id');
        $secretKey = $this->config('secret_access_key');
        $region = $this->config('region', 'us-east-1');

        // 注意: 生产环境应使用 aws/aws-sdk-php 进行 V4 签名
        // 以下为简化实现，实际部署时建议:
        // composer require aws/aws-sdk-php
        // $sdk = new \Aws\Sdk(['region' => $region, 'version' => 'latest']);
        // $client = $sdk->createMarketplaceMetering();

        $client = $this->httpClient([
            'base_uri' => $endpoint,
            'headers' => [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => "AWSMPMeteringService.{$params['Action']}",
            ],
        ]);

        $response = $client->post('/', [
            'json' => $params,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }
}
