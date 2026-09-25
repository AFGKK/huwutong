<?php

/**
 * 功能开关（优化方案 v1 · MVP 收敛）
 *
 * mvp_mode=true 时，后台菜单仅展示标注 mvp 的入口。
 * 可通过 .env FEATURES_MVP_MODE=false 恢复全量菜单。
 */
return [
    'mvp_mode' => (bool) env('FEATURES_MVP_MODE', true),

    // 单模块开关（供路由/API 使用；菜单以 mvp 标注为主）
    'modules' => [
        'blockchain_license' => (bool) env('FEATURE_BLOCKCHAIN_LICENSE', false),
        'license_marketplace' => (bool) env('FEATURE_LICENSE_MARKETPLACE', false),
        'chaos_engineering' => (bool) env('FEATURE_CHAOS_ENGINEERING', false),
        'blue_green' => (bool) env('FEATURE_BLUE_GREEN', false),
        'auto_pentest' => (bool) env('FEATURE_AUTO_PENTEST', false),
        'affiliate_enhanced' => (bool) env('FEATURE_AFFILIATE_ENHANCED', false),
        'cloud_marketplace' => (bool) env('FEATURE_CLOUD_MARKETPLACE', false),
        'grpc_istio' => (bool) env('FEATURE_GRPC_ISTIO', false),
    ],
];
