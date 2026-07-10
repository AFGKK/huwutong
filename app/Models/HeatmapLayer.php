<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperHeatmapLayer
 */
class HeatmapLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description',
        'data_source', 'type', 'config', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    const LAYER_TYPES = [
        'heatmap_scatter' => '散点热力图',
        'country_choropleth' => '国家色阶图',
        'region_bubble' => '区域气泡图',
    ];

    const DATA_SOURCES = [
        'license_activations' => 'License 激活',
        'product_usage' => '产品使用',
        'api_calls' => 'API 调用',
        'revenue' => '收入分布',
    ];
}
