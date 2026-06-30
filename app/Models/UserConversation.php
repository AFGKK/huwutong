<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConversation extends Model
{
    protected $fillable = ['type', 'name', 'created_by', 'last_message_id', 'last_message_at', 'slow_mode_interval', 'join_approval', 'permissions'];
    protected function casts(): array { return ['last_message_at' => 'datetime', 'slow_mode_interval' => 'integer', 'join_approval' => 'boolean', 'permissions' => 'array']; }

    const DEFAULT_PERMISSIONS = [
        'invite' => 'admin',
        'mention_all' => 'admin',
        'send_file' => 'all',
        'send_card' => 'all',
        'edit_group' => 'creator',
        'pin_message' => 'admin',
    ];

    public function participants(): HasMany { return $this->hasMany(ConversationParticipant::class, 'conversation_id'); }
    public function messages(): HasMany { return $this->hasMany(ConversationMessage::class, 'conversation_id'); }
    public function lastMessage(): BelongsTo { return $this->belongsTo(ConversationMessage::class, 'last_message_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * 检查用户是否有执行某项操作的权限
     */
    public function userCan(string $permission, int $userId): bool
    {
        $permConfig = array_merge(self::DEFAULT_PERMISSIONS, $this->permissions ?? []);
        $requiredLevel = $permConfig[$permission] ?? 'all';

        if ($requiredLevel === 'all') return true;

        $participant = $this->participants()->where('user_id', $userId)->first();
        if (!$participant) return false;

        $role = $participant->role;
        if ($requiredLevel === 'creator') return $role === 'creator';
        if ($requiredLevel === 'admin') return in_array($role, ['creator', 'admin']);

        return true;
    }

    /**
     * 获取合并后的权限配置
     */
    public function getEffectivePermissions(): array
    {
        return array_merge(self::DEFAULT_PERMISSIONS, $this->permissions ?? []);
    }
}
