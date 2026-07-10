<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOaPlatformAccount
 */
class OaPlatformAccount extends Model
{
    protected $table = 'oa_platform_accounts';

    protected $fillable = [
        'account_id', 'platform', 'label',
        'app_id', 'app_secret', 'access_token', 'refresh_token',
        'token_expires_at', 'platform_user_id', 'platform_user_name',
        'platform_avatar', 'is_verified', 'is_active', 'settings',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'json',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'account_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(OaArticleDistribution::class, 'platform_account_id');
    }
}
