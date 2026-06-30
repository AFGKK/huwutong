<?php

/**
 * License 生命周期管理配置 (M2-11 ~ M2-13)
 *
 * 统一管理回收站保留期、快照保留期、变更审批规则。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 回收站配置 (M2-13)
    |--------------------------------------------------------------------------
    */
    'trash' => [
        'retention_days' => env('LICENSE_TRASH_RETENTION_DAYS', 30),
        'auto_cleanup_enabled' => env('LICENSE_TRASH_AUTO_CLEANUP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 快照配置 (M2-12)
    |--------------------------------------------------------------------------
    */
    'snapshot' => [
        'retention_days' => env('LICENSE_SNAPSHOT_RETENTION_DAYS', 30),
        'auto_snapshot_before_change' => env('LICENSE_SNAPSHOT_AUTO', true),
        'auto_cleanup_enabled' => env('LICENSE_SNAPSHOT_AUTO_CLEANUP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 审批配置 (M2-11)
    |--------------------------------------------------------------------------
    */
    'approval' => [
        // 需要审批的操作列表
        'require_approval' => [
            'upgrade'       => env('LICENSE_APPROVAL_UPGRADE', true),
            'downgrade'     => env('LICENSE_APPROVAL_DOWNGRADE', true),
            'transfer'      => env('LICENSE_APPROVAL_TRANSFER', true),
            'seat_change'   => env('LICENSE_APPROVAL_SEAT_CHANGE', true),
            'type_change'   => env('LICENSE_APPROVAL_TYPE_CHANGE', true),
            'early_renewal' => env('LICENSE_APPROVAL_EARLY_RENEWAL', false),
        ],
        // 审批超时自动过期（小时）
        'expire_hours' => env('LICENSE_APPROVAL_EXPIRE_HOURS', 72),
        // 审批通知渠道
        'notify_channels' => ['database', 'mail'],
    ],
];
