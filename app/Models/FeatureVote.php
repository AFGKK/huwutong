<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFeatureVote
 */
class FeatureVote extends Model
{
    use HasFactory;

    protected $table = 'feature_votes';

    protected $fillable = [
        'feedback_id',
        'user_id',
        'tenant_id',
        'vote',
    ];

    protected function casts(): array
    {
        return [
            'vote' => 'integer',
        ];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(CustomerFeedback::class, 'feedback_id');
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
