<?php

// M2-130 客户侧审计日志 配置
return [
    'retention_days' => env('CUSTOMER_AUDIT_RETENTION', 90),
    'export_max_rows' => 10000,
    'pagination' => ['per_page' => 20, 'per_page_options' => [10, 20, 50, 100]],
];
