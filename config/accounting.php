<?php

return [
    /*
    | 会计系统集成配置
    | QuickBooks Online / Xero / 用友 / 金蝶
    */

    'quickbooks' => [
        'enabled' => env('QB_ENABLED', false),
        'client_id' => env('QB_CLIENT_ID', ''),
        'client_secret' => env('QB_CLIENT_SECRET', ''),
        'redirect_uri' => env('QB_REDIRECT_URI', env('APP_URL') . '/api/admin/accounting/oauth-callback/quickbooks'),
        'sandbox' => env('QB_SANDBOX', true),
        'company_id' => env('QB_COMPANY_ID', ''),
        'api_version' => 'v3',
    ],

    'xero' => [
        'enabled' => env('XERO_ENABLED', false),
        'client_id' => env('XERO_CLIENT_ID', ''),
        'client_secret' => env('XERO_CLIENT_SECRET', ''),
        'redirect_uri' => env('XERO_REDIRECT_URI', env('APP_URL') . '/api/admin/accounting/oauth-callback/xero'),
        'sandbox' => env('XERO_SANDBOX', true),
        'tenant_id' => env('XERO_TENANT_ID', ''),
    ],

    'yonyou' => [
        'enabled' => env('YONYOU_ENABLED', false),
        'api_endpoint' => env('YONYOU_API_ENDPOINT', ''),
        'client_id' => env('YONYOU_CLIENT_ID', ''),
        'client_secret' => env('YONYOU_CLIENT_SECRET', ''),
        'username' => env('YONYOU_USERNAME', ''),
        'password' => env('YONYOU_PASSWORD', ''),
        'account_set_id' => env('YONYOU_ACCOUNT_SET_ID', ''), // 账套ID
    ],

    'kingdee' => [
        'enabled' => env('KINGDEE_ENABLED', false),
        'api_endpoint' => env('KINGDEE_API_ENDPOINT', ''),
        'client_id' => env('KINGDEE_CLIENT_ID', ''),
        'client_secret' => env('KINGDEE_CLIENT_SECRET', ''),
        'username' => env('KINGDEE_USERNAME', ''),
        'password' => env('KINGDEE_PASSWORD', ''),
        'acct_id' => env('KINGDEE_ACCT_ID', ''), // 账套ID
    ],

    /*
    | 默认同步配置
    */
    'defaults' => [
        'sync_on_invoice_create' => true,
        'sync_on_payment' => true,
        'sync_on_refund' => true,
        'retry_failed' => true,
        'max_retry_count' => 3,
        'invoice_account_code' => env('ACCT_INVOICE_ACCOUNT', '4000'), // 销售收入科目
        'tax_account_code' => env('ACCT_TAX_ACCOUNT', '2221'),        // 应交税费科目
        'receivable_account_code' => env('ACCT_RECEIVABLE_ACCOUNT', '1122'), // 应收账款科目
    ],
];
