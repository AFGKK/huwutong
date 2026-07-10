<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperIdpGroupMapping
 */
class IdpGroupMapping extends Model
{
    protected $table = 'idp_group_mappings';
    protected $fillable = ['tenant_id', 'idp_id', 'idp_group_name', 'local_role', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function idp(): BelongsTo { return $this->belongsTo(EnterpriseIdp::class, 'idp_id'); }
}
