<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-01 离线激活记录
 *
 * 追踪每次离线 License 文件的生成和验证记录，
 * 用于审计和过期追溯。
 *
 * @property int $id
 * @property int|null $license_id
 * @property string $license_key
 * @property string|null $client_ip
 * @property string $result pending|valid|expired|revoked
 * @property array|null $payload_snapshot
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OfflineActivation extends Model
{
    protected $fillable = [
        'license_id',
        'license_key',
        'client_ip',
        'result',
        'payload_snapshot',
        'expires_at',
    ];

    protected $casts = [
        'payload_snapshot' => 'array',
        'expires_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
