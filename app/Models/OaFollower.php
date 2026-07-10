<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaFollower
 */
class OaFollower extends Model
{
    protected $table = 'follows';
    protected $fillable = ['user_id', 'followable_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->followable_type = 'App\\Models\\OfficialAccount';
    }

    public function scopeWhereAccountId($query, $accountId)
    {
        return $query->where('followable_type', 'App\\Models\\OfficialAccount')->where('followable_id', $accountId);
    }

    public function scopeWhereUserId($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'followable_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function create(array $attributes = [])
    {
        $attributes['followable_type'] = 'App\\Models\\OfficialAccount';
        return static::query()->create($attributes);
    }
}
