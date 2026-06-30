<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidgetTemplate extends Model
{
    protected $table = 'dashboard_widget_templates';

    protected $fillable = [
        'type', 'name', 'description', 'category',
        'default_config', 'default_visual_options', 'default_layout',
        'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_config' => 'array',
            'default_visual_options' => 'array',
            'default_layout' => 'array',
            'is_system' => 'boolean',
        ];
    }
}
