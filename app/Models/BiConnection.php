<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperBiConnection
 */
class BiConnection extends Model
{
    protected $table = 'bi_connections';

    protected $fillable = [
        'tenant_id', 'name', 'platform', 'status',
        'config', 'last_error', 'last_sync_at', 'last_success_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'last_sync_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    const PLATFORMS = [
        'snowflake' => 'Snowflake',
        'bigquery'  => 'Google BigQuery',
        'tableau'   => 'Tableau',
        'powerbi'   => 'Microsoft Power BI',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function datasets(): HasMany { return $this->hasMany(BiDataset::class, 'bi_connection_id'); }
}
