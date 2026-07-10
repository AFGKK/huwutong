<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCustomerClusterAssignment
 */
class CustomerClusterAssignment extends Model
{
    protected $table = 'customer_cluster_assignments';

    protected $fillable = [
        'tenant_id', 'customer_id', 'segment_key', 'score',
        'features', 'assigned_at', 'previous_segment_at',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'assigned_at' => 'datetime',
            'previous_segment_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
