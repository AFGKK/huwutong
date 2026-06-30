<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionLine extends Model
{
    protected $fillable = [
        'schedule_id', 'period_number', 'recognition_date',
        'amount', 'currency', 'description',
        'status', 'recognized_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recognition_date' => 'date',
            'recognized_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RevenueRecognitionSchedule::class, 'schedule_id');
    }
}
