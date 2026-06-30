<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditRetentionPolicy extends Model
{
    protected $table = 'audit_retention_policies';

    protected $fillable = [
        'type',
        'retention_days',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取某类型的有效保留天数（优先 DB 配置，其次 config/audit.php）
     */
    public static function getEffectiveDays(string $type): int
    {
        $policy = self::where('type', $type)->where('is_active', true)->first();
        if ($policy) {
            return $policy->retention_days;
        }

        return config("audit.retention_days.{$type}", 365);
    }

    /**
     * 获取所有类型的有效保留天数
     */
    public static function getAllEffectiveDays(): array
    {
        $defaults = config('audit.retention_days', [
            'audit' => 365,
            'security' => 365,
            'error' => 180,
            'system' => 90,
        ]);

        $policies = self::where('is_active', true)->get()->keyBy('type');

        foreach ($defaults as $type => $days) {
            if (isset($policies[$type])) {
                $defaults[$type] = $policies[$type]->retention_days;
            }
        }

        return $defaults;
    }
}
