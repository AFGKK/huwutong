<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLicenseDispute
 */
class LicenseDispute extends Model
{
    protected $table = 'license_disputes';

    protected $fillable = [
        'transaction_id', 'raised_by', 'type', 'description',
        'evidence', 'status', 'resolution', 'resolution_notes',
        'resolved_by', 'resolved_at', 'auto_resolve_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'resolved_at' => 'datetime',
            'auto_resolve_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo { return $this->belongsTo(LicenseTransaction::class, 'transaction_id'); }
    public function raiser(): BelongsTo { return $this->belongsTo(Customer::class, 'raised_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
