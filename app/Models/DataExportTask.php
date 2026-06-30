<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 数据导出任务记录 (M2-139)
 *
 * 记录每个导出/匿名化任务的执行情况。
 */
class DataExportTask extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const TYPE_EXPORT = 'export';      // 导出到 Staging
    const TYPE_ANONYMIZE = 'anonymize'; // 仅匿名化

    protected $fillable = [
        'type',
        'status',
        'source_connection',
        'target_connection',
        'tables',
        'total_records',
        'processed_records',
        'anonymized_tables',
        'excluded_tables',
        'output_file',
        'file_size',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'tables' => 'array',
        'anonymized_tables' => 'array',
        'excluded_tables' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 任务是否完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 任务是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 任务是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * 获取进度百分比
     */
    public function getProgressPercent(): int
    {
        if ($this->total_records <= 0) {
            return 0;
        }

        return (int) round(($this->processed_records / $this->total_records) * 100);
    }

    /**
     * 标记为运行中
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * 标记为完成
     */
    public function markAsCompleted(string $outputFile = null, int $fileSize = 0): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'output_file' => $outputFile,
            'file_size' => $fileSize,
        ]);
    }

    /**
     * 标记为失败
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * 更新处理进度
     */
    public function updateProgress(int $processed): void
    {
        $this->update([
            'processed_records' => $processed,
        ]);
    }
}
