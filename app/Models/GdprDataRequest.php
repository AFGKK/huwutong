<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GDPR 数据主体请求（Data Subject Request）(M3-33)
 *
 * 支持：访问权/数据可移植性导出/更正/删除/限制处理/反对自动化决策
 *
 * @mixin IdeHelperGdprDataRequest
 */
class GdprDataRequest extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    const TYPE_ACCESS = 'access';           // 数据访问权 Art.15
    const TYPE_EXPORT = 'export';           // 数据可移植性 Art.20
    const TYPE_RECTIFICATION = 'rectification'; // 更正权 Art.16
    const TYPE_ERASURE = 'erasure';         // 被遗忘权 Art.17
    const TYPE_RESTRICT = 'restrict';       // 限制处理 Art.18
    const TYPE_PORTABILITY = 'portability'; // 数据可移植性 Art.20
    const TYPE_OBJECT = 'object';           // 反对权 Art.21

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'request_data',
        'reason',
        'output_file',
        'file_size',
        'expires_at',
        'completed_at',
        'processed_by',
        'admin_notes',
        'rejection_reason',
    ];

    protected $casts = [
        'request_data' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * 获取类型的中文标签
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_ACCESS => '数据访问',
            self::TYPE_EXPORT => '数据导出',
            self::TYPE_RECTIFICATION => '数据更正',
            self::TYPE_ERASURE => '数据删除',
            self::TYPE_RESTRICT => '限制处理',
            self::TYPE_PORTABILITY => '数据可移植性',
            self::TYPE_OBJECT => '反对处理',
            default => $this->type,
        };
    }

    /**
     * 获取状态的标签
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_APPROVED => '已批准',
            self::STATUS_REJECTED => '已拒绝',
            self::STATUS_FAILED => '失败',
            default => $this->status,
        };
    }

    /**
     * 请求是否可处理
     */
    public function isProcessable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_FAILED]);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
