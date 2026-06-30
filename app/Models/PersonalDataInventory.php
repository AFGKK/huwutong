<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 个人信息分类分级清单 (PIPL)
 *
 * 记录每个字段的分类（一般/敏感/私密）和分级（L1-L4）
 */
class PersonalDataInventory extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    const CATEGORY_PERSON = 'person';       // 个人信息
    const CATEGORY_GENERAL = 'general';     // 一般信息
    const CATEGORY_SENSITIVE = 'sensitive'; // 敏感个人信息
    const CATEGORY_PRIVATE = 'private';     // 私密信息

    const CLASS_L1 = 'L1'; // 一级（公开）
    const CLASS_L2 = 'L2'; // 二级（内部）
    const CLASS_L3 = 'L3'; // 三级（敏感）
    const CLASS_L4 = 'L4'; // 四级（核心）

    protected $fillable = [
        'tenant_id', 'field_name', 'table_name',
        'category', 'classification', 'purpose',
        'retention_days', 'is_required', 'is_exportable', 'is_deletable',
        'status',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_exportable' => 'boolean',
        'is_deletable' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
