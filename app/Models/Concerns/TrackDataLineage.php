<?php

namespace App\Models\Concerns;

use App\Models\DataLineageRecord;
use Illuminate\Support\Str;

/**
 * 数据血缘追踪 Trait
 *
 * 在模型中使用: use TrackDataLineage;
 * 然后重写 lineageConfig() 定义追踪规则。
 */
trait TrackDataLineage
{
    /**
     * Boot trait: 注册模型事件监听
     */
    protected static function bootTrackDataLineage(): void
    {
        // 创建后记录
        static::created(function ($model) {
            $model->recordLineage('created');
        });

        // 更新后记录变更
        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getLineageTrackedFields() as $field => $label) {
                if ($model->wasChanged($field)) {
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'old' => $model->getOriginal($field),
                        'new' => $model->getAttribute($field),
                    ];
                }
            }
            if (!empty($changes)) {
                $model->recordLineage('updated', ['changes' => $changes]);
            }
        });

        // 软删除 / 硬删除
        static::deleted(function ($model) {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'deleted'
                : 'archived';
            $model->recordLineage($event);
        });

        // 软恢复
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->recordLineage('restored');
            });
        }
    }

    /**
     * 定义追踪配置
     *
     * @return array [
     *   'category' => 'license_key|pii|device_fingerprint|...',
     *   'sensitivity' => 'public|internal|confidential|restricted',
     *   'label' => fn($model) => '可读标签',
     *   'fields' => ['field_name' => '字段标签', ...]  // 追踪的字段
     * ]
     */
    abstract protected function lineageConfig(): array;

    /**
     * 获取追踪的字段列表
     */
    protected function getLineageTrackedFields(): array
    {
        $config = $this->lineageConfig();
        return $config['fields'] ?? [];
    }

    /**
     * 记录一条数据血缘事件
     */
    public function recordLineage(string $eventType, array $extra = []): ?DataLineageRecord
    {
        $config = $this->lineageConfig();

        $data = array_merge([
            'tenant_id' => $this->tenant_id ?? auth()->user()?->tenant_id,
            'trackable_type' => $config['trackable_type'] ?? $this->getTable(),
            'trackable_id' => (string) $this->getKey(),
            'trackable_label' => is_callable($config['label'] ?? null)
                ? call_user_func($config['label'], $this)
                : ($config['label'] ?? null),
            'data_category' => $config['category'] ?? 'general',
            'sensitivity' => $config['sensitivity'] ?? 'internal',
            'event_type' => $eventType,
            'event_label' => $extra['event_label'] ?? $this->getLineageEventLabel($eventType),
            'source_system' => $extra['source_system'] ?? 'system',
            'actor_id' => $extra['actor_id'] ?? auth()->id(),
            'actor_type' => $extra['actor_type'] ?? (auth()->id() ? 'user' : 'system'),
            'changes' => $extra['changes'] ?? null,
            'metadata' => $extra['metadata'] ?? null,
            'trace_id' => $extra['trace_id'] ?? (string) Str::uuid(),
        ], $extra);

        return DataLineageRecord::create($data);
    }

    /**
     * 获取事件的可读标签
     */
    protected function getLineageEventLabel(string $eventType): string
    {
        $labels = [
            'created' => '创建',
            'updated' => '更新',
            'deleted' => '删除',
            'archived' => '归档',
            'restored' => '恢复',
            'activated' => '激活',
            'validated' => '验证',
            'revoked' => '撤销',
            'exported' => '导出',
            'transferred' => '转移',
            'drifted' => '指纹漂移',
        ];
        return $labels[$eventType] ?? $eventType;
    }

    /**
     * 获取该模型的血缘记录
     */
    public function lineageRecords()
    {
        $config = $this->lineageConfig();
        return $this->morphMany(
            DataLineageRecord::class,
            'trackable',
            'trackable_type',
            'trackable_id'
        )->where('trackable_type', $config['trackable_type'] ?? $this->getTable());
    }
}
