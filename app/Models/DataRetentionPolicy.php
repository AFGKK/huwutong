<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperDataRetentionPolicy
 */
class DataRetentionPolicy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key', 'name', 'category', 'table_name', 'retention_days',
        'action', 'archive_enabled', 'archive_after_days',
        'archive_storage_tier', 'archive_disk', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'archive_after_days' => 'integer',
        'archive_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    const ACTIONS = ['archive', 'delete', 'anonymize'];
    const CATEGORIES = ['audit', 'security', 'operation', 'notification', 'performance'];
    const TIERS = ['hot', 'warm', 'cold', 'frozen', 'deep_frozen'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * 获取此策略关联的数据表清理查询
     */
    public function getCleanupQuery(): ?string
    {
        if (!$this->table_name) {
            return null;
        }

        $cutoff = now()->subDays($this->retention_days)->toDateTimeString();

        return match ($this->action) {
            'delete' => "DELETE FROM {$this->table_name} WHERE created_at < '{$cutoff}'",
            'archive' => "SELECT * FROM {$this->table_name} WHERE created_at < '{$cutoff}'",
            default => null,
        };
    }
}
