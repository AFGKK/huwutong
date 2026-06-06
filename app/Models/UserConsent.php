<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserConsent extends Model
{
    protected $fillable = [
        'user_id', 'legal_consent_id', 'ip_address', 'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function legalConsent()
    {
        return $this->belongsTo(LegalConsent::class);
    }
}
