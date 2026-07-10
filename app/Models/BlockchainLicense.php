<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperBlockchainLicense
 */
class BlockchainLicense extends Model
{
    use SoftDeletes;

    protected $table = 'blockchain_licenses';

    protected $fillable = [
        'tenant_id', 'license_id', 'chain', 'contract_address',
        'token_id', 'token_uri', 'wallet_address', 'owner_address',
        'transaction_hash', 'minted_at', 'last_sync_at',
        'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'minted_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
}
