<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 数据匿名化规则 (M2-139)
 *
 * 存储用户自定义的表/字段匿名化规则，覆盖 config/data-anonymization.php 中的默认规则。
 */
class DataAnonymizationRule extends Model
{
    protected $fillable = [
        'table_name',
        'field_name',
        'method',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 获取指定表的活跃规则
     */
    public static function getActiveRulesForTable(string $tableName): array
    {
        return static::where('table_name', $tableName)
            ->where('is_active', true)
            ->get()
            ->keyBy('field_name')
            ->toArray();
    }
}
