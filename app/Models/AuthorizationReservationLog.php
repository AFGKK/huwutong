<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationReservationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reservation_id',
        'action',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(AuthorizationReservation::class, 'reservation_id');
    }
}
