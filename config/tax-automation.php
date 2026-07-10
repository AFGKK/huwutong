<?php

return [
    /*
    | 税务自动化配置
    */

    // 默认税务计算方式: local | taxjar | avalara | stripe
    'default_provider' => env('TAX_DEFAULT_PROVIDER', 'local'),

    // TaxJar (美国 Sales Tax)
    'taxjar' => [
        'enabled' => env('TAXJAR_ENABLED', false),
        'api_key' => env('TAXJAR_API_KEY', ''),
        'sandbox' => env('TAXJAR_SANDBOX', true),
    ],

    // Stripe Tax
    'stripe' => [
        'enabled' => env('STRIPE_TAX_ENABLED', false),
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
    ],

    // Avalara (全球 VAT/GST/Sales Tax)
    'avalara' => [
        'enabled' => env('AVALARA_ENABLED', false),
        'account_id' => env('AVALARA_ACCOUNT_ID', ''),
        'license_key' => env('AVALARA_LICENSE_KEY', ''),
        'company_code' => env('AVALARA_COMPANY_CODE', ''),
        'sandbox' => env('AVALARA_SANDBOX', true),
    ],

    // 中国电子发票
    'china_einvoice' => [
        'enabled' => env('CHINA_EINVOICE_ENABLED', false),
        // 发票通
        'fapiao_tong' => [
            'provider' => env('CHINA_EINVOICE_PROVIDER', 'fapiaotong'), // fapiaotong | baiwang | hangxin
            'app_key' => env('FAPIAOTONG_APP_KEY', ''),
            'app_secret' => env('FAPIAOTONG_APP_SECRET', ''),
            'endpoint' => env('FAPIAOTONG_ENDPOINT', 'https://api.fapiaotong.com/v2'),
            'taxpayer_id' => env('FAPIAOTONG_TAXPAYER_ID', ''),  // 纳税人识别号
        ],
        // 百旺/航信税控
        'tax_control' => [
            'endpoint' => env('TAX_CONTROL_ENDPOINT', ''),
            'username' => env('TAX_CONTROL_USERNAME', ''),
            'password' => env('TAX_CONTROL_PASSWORD', ''),
            'device_id' => env('TAX_CONTROL_DEVICE_ID', ''),     // 税控盘编号
        ],
    ],

    // 卖家注册信息（用于跨境税务）
    'seller' => [
        'country_code' => env('SELLER_COUNTRY', 'CN'),
        'vat_number' => env('SELLER_VAT_NUMBER', ''),
        'tax_id' => env('SELLER_TAX_ID', ''),
        'company_name' => env('SELLER_COMPANY_NAME', ''),
        'address' => env('SELLER_ADDRESS', ''),
        'eu_vat_number' => env('SELLER_EU_VAT_NUMBER', ''),    // EU VAT号（OSS使用）
        'oss_country' => env('SELLER_OSS_COUNTRY', ''),         // OSS注册国
    ],

    // 默认科目代码（映射到会计系统）
    'accounts' => [
        'output_tax' => env('ACCT_OUTPUT_TAX', '2221'),          // 销项税
        'input_tax' => env('ACCT_INPUT_TAX', '2222'),            // 进项税
        'tax_receivable' => env('ACCT_TAX_RECEIVABLE', '1123'),  // 应收税费
        'vat_payable' => env('ACCT_VAT_PAYABLE', '2223'),        // 应交增值税
    ],
];
