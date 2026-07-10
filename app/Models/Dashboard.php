<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDashboard
 */
class Dashboard extends Model
{
    protected $fillable = [
        'user_id', 'tenant_id', 'name', 'description',
        'layout_type', 'layout_config', 'columns',
        'sort_order', 'is_default', 'is_shared', 'tags',
    ];

    protected function casts(): array
    {
        return [
            'layout_config' => 'array',
            'is_default' => 'boolean',
            'is_shared' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function widgets(): HasMany { return $this->hasMany(DashboardWidget::class)->orderBy('sort_order'); }
}
