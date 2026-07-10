<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 混沌工程实验模型
 *
 * 记录混沌实验的定义、执行和结果
 *
 * @m3-80 ChaosEngineering
 * @mixin IdeHelperChaosExperiment
 */
class ChaosExperiment extends Model
{
    protected $fillable = [
        'title',
        'description',
        'experiment_type',    // redis_outage, db_failover, pod_kill, network_latency, disk_full, cpu_stress, memory_stress
        'target_service',     // redis, database, api, queue, reverb, all
        'target_namespace',   // K8s namespace
        'fault_config',       // JSON: 故障参数配置
        'scope',              // 影响范围: single_pod, multi_pod, service, namespace
        'blast_radius',       // 爆炸半径: low, medium, high, critical
        'status',             // draft, scheduled, running, completed, failed, rolled_back
        'scheduled_at',
        'executed_at',
        'completed_at',
        'duration_seconds',
        'expected_behavior',
        'actual_behavior',
        'degradation_verified', // boolean: 降级行为是否按预期触发
        'auto_recovery_verified', // boolean: 自动恢复是否正常
        'resilience_score',     // int: 0-100
        'findings',             // JSON: 发现的问题
        'improvements',         // JSON: 改进项
        'executed_by',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fault_config' => 'array',
            'scheduled_at' => 'datetime',
            'executed_at' => 'datetime',
            'completed_at' => 'datetime',
            'degradation_verified' => 'boolean',
            'auto_recovery_verified' => 'boolean',
            'resilience_score' => 'integer',
            'duration_seconds' => 'integer',
            'findings' => 'array',
            'improvements' => 'array',
        ];
    }

    /**
     * 实验类型列表
     */
    const TYPES = [
        'redis_outage' => ['name' => 'Redis 宕机', 'icon' => 'redis', 'risk' => 'high'],
        'db_failover' => ['name' => 'DB 主从切换', 'icon' => 'db', 'risk' => 'critical'],
        'pod_kill' => ['name' => 'K8s Pod 随机 Kill', 'icon' => 'k8s', 'risk' => 'medium'],
        'network_latency' => ['name' => '网络延迟注入', 'icon' => 'network', 'risk' => 'medium'],
        'disk_full' => ['name' => '磁盘满载模拟', 'icon' => 'disk', 'risk' => 'high'],
        'cpu_stress' => ['name' => 'CPU 压力测试', 'icon' => 'cpu', 'risk' => 'medium'],
        'memory_stress' => ['name' => '内存压力测试', 'icon' => 'memory', 'risk' => 'medium'],
    ];

    /**
     * 状态标签
     */
    const STATUSES = [
        'draft' => '草稿',
        'scheduled' => '已计划',
        'running' => '运行中',
        'completed' => '已完成',
        'failed' => '失败',
        'rolled_back' => '已回滚',
    ];

    public function scopeByType($query, string $type)
    {
        return $query->where('experiment_type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }
}
