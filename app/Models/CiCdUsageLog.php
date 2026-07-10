<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCiCdUsageLog
 */
class CiCdUsageLog extends Model
{
    protected $table = 'ci_cd_usage_logs';

    protected $fillable = [
        'ci_cd_token_id', 'action', 'ci_provider', 'repository',
        'workflow', 'runner_os', 'ip_address', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(CiCdToken::class, 'ci_cd_token_id');
    }
}
