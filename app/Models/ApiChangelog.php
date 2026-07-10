<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApiChangelog
 */
class ApiChangelog extends Model
{
    protected $table = 'api_changelogs';

    protected $fillable = [
        'version', 'release_date', 'type', 'title',
        'description', 'affected_endpoints', 'migration_guide',
        'source', 'snapshot_id',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'affected_endpoints' => 'array',
        ];
    }
}
