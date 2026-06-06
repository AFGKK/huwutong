<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnounceBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'position',
        'can_close',
        'link_url',
        'link_text',
        'roles',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'can_close' => 'boolean',
            'roles' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 判断当前是否在展示时间窗口内
     */
    public function isInTimeWindow(): bool
    {
        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * 判断指定角色是否可见
     */
    public function isVisibleToRole(?string $role): bool
    {
        if (empty($this->roles)) {
            return true;
        }

        return in_array($role, $this->roles);
    }
}
