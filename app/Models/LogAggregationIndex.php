<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAggregationIndex extends Model
{
    protected $fillable = [
        'index_name', 'source', 'level', 'log_date', 'count', 'sample',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'datetime',
            'sample' => 'array',
        ];
    }

    public function entries()
    {
        return $this->hasMany(LogAggregationEntry::class, 'index_id');
    }
}
