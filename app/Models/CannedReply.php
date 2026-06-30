<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CannedReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'canned_replies';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'title',
        'content',
        'category',
        'shortcuts',
        'sort_order',
        'is_shared',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shortcuts' => 'array',
            'sort_order' => 'integer',
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
