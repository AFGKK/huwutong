<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperJitProvisioningRule
 */
class JitProvisioningRule extends Model
{
    protected $table = 'jit_provisioning_rules';
    protected $fillable = [
        'tenant_id', 'idp_id', 'name', 'is_active',
        'default_role', 'auto_create_users', 'auto_update_attributes',
        'email_domain_filter', 'attribute_mapping',
    ];
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_create_users' => 'boolean',
            'auto_update_attributes' => 'boolean',
            'attribute_mapping' => 'array',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function idp(): BelongsTo { return $this->belongsTo(EnterpriseIdp::class, 'idp_id'); }
}
