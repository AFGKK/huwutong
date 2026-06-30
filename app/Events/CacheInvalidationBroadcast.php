<?php

namespace App\Events;

use App\Models\CacheInvalidation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CacheInvalidationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 缓存失效记录
     */
    public CacheInvalidation $invalidation;

    /**
     * 租户 ID
     */
    public int $tenantId;

    /**
     * 批量失效键列表（合并推送时使用）
     */
    public array $keys;

    /**
     * Create a new event instance.
     */
    public function __construct(CacheInvalidation $invalidation, array $keys = [])
    {
        $this->invalidation = $invalidation;
        $this->tenantId = $invalidation->tenant_id;
        $this->keys = $keys;
    }

    /**
     * 广播频道 — 租户级私有频道
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sdk-cache.tenant.' . $this->tenantId),
        ];
    }

    /**
     * 广播事件名称
     */
    public function broadcastAs(): string
    {
        return 'cache.invalidation';
    }

    /**
     * 发送到前端/SDK 的数据
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->invalidation->type,
            'invalidation_key' => $this->invalidation->invalidation_key,
            'keys' => $this->keys,
            'context' => $this->invalidation->context,
            'timestamp' => $this->invalidation->created_at?->toIso8601String(),
            'batch' => ! empty($this->keys),
        ];
    }
}
