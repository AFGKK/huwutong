<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensitiveWord extends Model
{
    protected $fillable = ['word', 'replacement', 'category', 'severity', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeByCategory($q, $cat) { return $q->where('category', $cat); }
}
