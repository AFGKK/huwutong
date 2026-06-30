<?php

// M2-132 API 用量分端点统计 配置
return [
    'retention_days' => 365,
    'alert_threshold' => env('ENDPOINT_USAGE_ALERT_THRESHOLD', 80), // 用量超限告警百分比
    'pagination' => ['per_page' => 20],
];
