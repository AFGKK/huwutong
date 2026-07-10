<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCsmCommunication
 */
class CsmCommunication extends Model
{
    const TYPES = [
        'call' => '电话',
        'email' => '邮件',
        'meeting' => '会议',
        'note' => '备注',
        'chat' => '在线聊天',
    ];

    protected $table = 'csm_communications';

    protected $fillable = [
        'tenant_id', 'customer_id', 'user_id',
        'type', 'subject', 'content', 'attachments', 'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'contacted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
