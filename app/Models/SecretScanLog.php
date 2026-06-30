<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecretScanLog extends Model
{
    protected $fillable = [
        'file',
        'pattern_label',
        'matched_preview',
        'severity',
        'status',
        'note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }

    public function scopeCritical($q)
    {
        return $q->where('severity', 'critical');
    }
}
