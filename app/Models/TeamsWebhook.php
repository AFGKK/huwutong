<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $webhook_url
 * @property string $notification_type
 * @property bool $is_active
 * @property array|null $filters
 * @property string|null $description
 * @property string|null $last_sent_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @mixin IdeHelperTeamsWebhook
 */
class TeamsWebhook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'webhook_url',
        'notification_type',
        'is_active',
        'filters',
        'description',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'filters' => 'array',
            'last_sent_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TeamsNotificationLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('notification_type', 'all')
              ->orWhere('notification_type', $type);
        });
    }

    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
