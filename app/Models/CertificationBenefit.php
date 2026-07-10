<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 开发者认证权益模型 (M3-58)
 *
 * 每个认证等级关联的权益
 *
 * @mixin IdeHelperCertificationBenefit
 */
class CertificationBenefit extends Model
{
    protected $fillable = [
        'certification_level_id',
        'title',
        'description',
        'icon',
        'type',          // support, resource, access, badge, promotion
        'value',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function level()
    {
        return $this->belongsTo(CertificationLevel::class, 'certification_level_id');
    }
}
