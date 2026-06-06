<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordPolicyConfig extends Model
{
    protected $fillable = [
        'min_length', 'max_length',
        'require_uppercase', 'require_lowercase',
        'require_number', 'require_special',
        'history_count', 'expiry_days',
        'lockout_max_attempts', 'lockout_duration_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'require_uppercase' => 'boolean',
            'require_lowercase' => 'boolean',
            'require_number' => 'boolean',
            'require_special' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取当前生效的策略（只会有一条记录）
     */
    public static function getActive(): self
    {
        $config = self::where('is_active', true)->first();

        if (!$config) {
            $config = self::create([
                'min_length' => 8,
                'max_length' => 128,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_number' => true,
                'require_special' => true,
                'history_count' => 5,
                'expiry_days' => 90,
                'lockout_max_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'is_active' => true,
            ]);
        }

        return $config;
    }
}
