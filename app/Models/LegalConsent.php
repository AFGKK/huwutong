<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLegalConsent
 */
class LegalConsent extends Model
{
    protected $fillable = [
        'type', 'version', 'content', 'is_current', 'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'effective_at' => 'datetime',
        ];
    }

    /**
     * 获取当前生效的协议版本
     */
    public static function getCurrent(string $type): ?self
    {
        return static::where('type', $type)
            ->where('is_current', true)
            ->first();
    }

    /**
     * 用户是否已确认此协议
     */
    public function isConsentedBy(int $userId): bool
    {
        return UserConsent::where('user_id', $userId)
            ->where('legal_consent_id', $this->id)
            ->exists();
    }
}
