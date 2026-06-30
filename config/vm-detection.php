<?php

/**
 * 虚拟环境/模拟器检测配置 (M1.3-14)
 *
 * 检测运行环境是否为虚拟机/容器/模拟器，
 * 可选禁止激活或降低信任分。
 */

return [
    /*
     * 启用/停用检测
     */
    'enabled' => env('VM_DETECTION_ENABLED', true),

    /*
     * 检测策略
     * - block: 检测到虚拟环境则禁止激活
     * - reduce_trust: 检测到虚拟环境仅降低信任分
     * - log_only: 仅记录日志，不干预
     */
    'strategy' => env('VM_DETECTION_STRATEGY', 'reduce_trust'),

    /*
     * 虚拟环境信任分
     * strategy=reduce_trust 时，检测到虚拟环境后的设备信任分
     */
    'vm_trust_score' => 20,

    /*
     * 检测项启用开关
     */
    'checks' => [
        'docker' => true,
        'vmware' => true,
        'virtualbox' => true,
        'kvm' => true,
        'hyper_v' => true,
        'xen' => true,
        'qemu' => true,
        'parallels' => true,
        'wsl' => true,
        'android_emulator' => true,
        'ios_simulator' => true,
        'container' => true,
    ],

    /*
     * 检测阈值
     * 命中检测项数量达到此阈值即判定为虚拟环境
     */
    'detection_threshold' => 2,

    /*
     * 缓存 TTL（秒）
     */
    'cache_ttl' => 3600,

    /*
     * 监控配置
     */
    'monitoring' => [
        'log_detections' => true,
        'alert_on_detection' => true,
        'alert_channels' => ['notification', 'webhook'],
    ],
];
