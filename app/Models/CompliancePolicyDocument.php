<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 安全策略文档模板
 *
 * SOC2/ISO27001 审计所需的安全策略文档模板。
 * 用户可基于模板填写组织特定内容并生成正式文档。
 *
 * @m3-69 CompliancePack
 */
class CompliancePolicyDocument extends Model
{
    protected $fillable = [
        'framework_code',
        'category',
        'doc_key',
        'title',
        'description',
        'content_template',
        'placeholder_fields',
        'version',
        'is_active',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'placeholder_fields' => 'array',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * 范围：按框架
     */
    public function scopeFramework($query, string $code)
    {
        return $query->where('framework_code', $code);
    }

    /**
     * 范围：按分类
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
