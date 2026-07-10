<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperIdpDomainRoute
 */
class IdpDomainRoute extends Model
{
    protected $table = 'idp_domain_routes';
    protected $fillable = ['tenant_id', 'idp_id', 'domain', 'is_primary'];
    protected function casts(): array { return ['is_primary' => 'boolean']; }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function idp(): BelongsTo { return $this->belongsTo(EnterpriseIdp::class, 'idp_id'); }
}
