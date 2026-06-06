<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantMember extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'role',
        'invited_by', 'status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
