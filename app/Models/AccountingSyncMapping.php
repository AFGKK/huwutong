<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAccountingSyncMapping
 */
class AccountingSyncMapping extends Model
{
    protected $table = 'accounting_sync_mappings';

    protected $fillable = [
        'tenant_id', 'integration_id', 'local_type', 'local_id',
        'remote_id', 'remote_number', 'status', 'error_message', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(AccountingIntegration::class, 'integration_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 获取关联的本地模型
     */
    public function localModel(): ?Model
    {
        return match ($this->local_type) {
            'invoice' => Invoice::find($this->local_id),
            'payment' => Payment::find($this->local_id),
            'refund'  => Refund::find($this->local_id),
            'customer' => Customer::find($this->local_id),
            default => null,
        };
    }
}
