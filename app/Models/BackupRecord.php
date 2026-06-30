<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 备份记录
 */
class BackupRecord extends Model
{
    protected $fillable = [
        'name', 'type', 'status', 'file_path', 'file_name', 'file_size',
        'disk', 'checksum', 'database', 'included_tables', 'excluded_tables',
        'error_message', 'duration_seconds', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'included_tables' => 'array',
            'excluded_tables' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function fileIncludes(): HasMany
    {
        return $this->hasMany(BackupFileInclude::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'running']);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'completed')
            ->where('expires_at', '<=', now());
    }

    /**
     * 格式化文件大小
     */
    public function getFormattedSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '-';
        }
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
