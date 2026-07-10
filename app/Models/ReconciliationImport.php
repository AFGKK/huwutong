<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperReconciliationImport
 */
class ReconciliationImport extends Model
{
    protected $fillable = [
        'tenant_id', 'channel', 'filename', 'total_rows', 'matched_rows',
        'unmatched_rows', 'error_rows', 'status', 'error_message', 'summary', 'imported_by',
    ];

    protected $casts = [
        'summary' => 'array',
        'imported_at' => 'datetime',
    ];

    public const CHANNELS = ['wechat', 'alipay', 'stripe', 'paypal'];

    public function rows(): HasMany
    {
        return $this->hasMany(ReconciliationChannelRow::class, 'import_id');
    }
}
