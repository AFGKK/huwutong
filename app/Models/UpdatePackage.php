<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperUpdatePackage
 */
class UpdatePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'version',
        'prev_version',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'file_hash',
        'signature',
        'checksums',
        'release_notes',
        'metadata',
        'status',
        'published_at',
        'deprecated_at',
        'created_by',
    ];

    protected $casts = [
        'checksums' => 'json',
        'release_notes' => 'json',
        'metadata' => 'json',
        'file_size' => 'integer',
        'published_at' => 'datetime',
        'deprecated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(UpdatePackageDownload::class);
    }

    /**
     * 发布此更新包
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * 标记为废弃
     */
    public function deprecate(): bool
    {
        return $this->update([
            'status' => 'deprecated',
            'deprecated_at' => now(),
        ]);
    }

    /**
     * 获取下载 URL
     */
    public function downloadUrl(): string
    {
        return route('api.update-packages.download', $this->id);
    }

    /**
     * 是否是增量补丁
     */
    public function isIncremental(): bool
    {
        return $this->type === 'incremental';
    }

    /**
     * 获取友好的文件大小描述
     */
    public function fileSizeForHumans(): string
    {
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
