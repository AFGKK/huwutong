<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperIdpHealthLog
 */
class IdpHealthLog extends Model
{
    protected $table = 'idp_health_logs';
    protected $fillable = ['tenant_id', 'idp_id', 'check_type', 'is_healthy', 'message', 'details', 'checked_at'];
    protected function casts(): array { return ['is_healthy' => 'boolean', 'details' => 'array', 'checked_at' => 'datetime']; }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function idp(): BelongsTo { return $this->belongsTo(EnterpriseIdp::class, 'idp_id'); }
}
