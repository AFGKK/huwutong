<?php

return [
    /*
    | 云市场集成配置
    | 支持 AWS Marketplace / Azure Marketplace / GCP Marketplace
    */

    'aws' => [
        'enabled' => env('AWS_MARKETPLACE_ENABLED', false),
        'sns_topic_arn' => env('AWS_MARKETPLACE_SNS_TOPIC_ARN', ''),
        'sqs_queue_url' => env('AWS_MARKETPLACE_SQS_QUEUE_URL', ''),
        'metering_endpoint' => env('AWS_MARKETPLACE_METERING_ENDPOINT', 'https://metering.marketplace.amazonaws.com'),
        'entitlement_endpoint' => env('AWS_MARKETPLACE_ENTITLEMENT_ENDPOINT', 'https://entitlement.marketplace.amazonaws.com'),
        'region' => env('AWS_MARKETPLACE_REGION', 'us-east-1'),
        'access_key_id' => env('AWS_MARKETPLACE_ACCESS_KEY_ID', ''),
        'secret_access_key' => env('AWS_MARKETPLACE_SECRET_ACCESS_KEY', ''),
        'sns_notification_url' => env('APP_URL') . '/api/marketplace/aws/sns',
        'return_url' => env('APP_URL') . '/marketplace/aws/return',
    ],

    'azure' => [
        'enabled' => env('AZURE_MARKETPLACE_ENABLED', false),
        'tenant_id' => env('AZURE_MARKETPLACE_TENANT_ID', ''),
        'client_id' => env('AZURE_MARKETPLACE_CLIENT_ID', ''),
        'client_secret' => env('AZURE_MARKETPLACE_CLIENT_SECRET', ''),
        'fulfillment_endpoint' => 'https://marketplaceapi.microsoft.com/api/saas',
        'metering_endpoint' => 'https://marketplaceapi.microsoft.com/api/metering',
        'webhook_url' => env('APP_URL') . '/api/marketplace/azure/webhook',
        'return_url' => env('APP_URL') . '/marketplace/azure/return',
    ],

    'gcp' => [
        'enabled' => env('GCP_MARKETPLACE_ENABLED', false),
        'service_account_json' => env('GCP_MARKETPLACE_SERVICE_ACCOUNT', ''),
        'project_id' => env('GCP_MARKETPLACE_PROJECT_ID', ''),
        'pubsub_topic' => env('GCP_MARKETPLACE_PUBSUB_TOPIC', ''),
        'pubsub_subscription' => env('GCP_MARKETPLACE_PUBSUB_SUBSCRIPTION', ''),
        'return_url' => env('APP_URL') . '/marketplace/gcp/return',
    ],

    /*
    | 公共设置
    */
    'currency' => env('MARKETPLACE_CURRENCY', 'USD'),
    'metering_batch_size' => env('MARKETPLACE_METERING_BATCH_SIZE', 25),
    'metering_interval_minutes' => env('MARKETPLACE_METERING_INTERVAL', 60),
];
