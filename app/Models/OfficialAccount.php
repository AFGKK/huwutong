<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OfficialAccount extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'avatar', 'cover_image', 'owner_id', 'category_id', 'status', 'settings'];
    protected $casts = ['settings' => 'array'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function category(): BelongsTo { return $this->belongsTo(OaCategory::class); }
    public function followers(): MorphMany { return $this->morphMany(Follow::class, 'followable'); }
    public function articles(): HasMany { return $this->hasMany(OaArticle::class, 'account_id'); }
    public function submissions(): HasMany { return $this->hasMany(OaSubmission::class, 'account_id'); }
    public function products(): HasMany { return $this->hasMany(Product::class, 'merchant_id'); }
    public function followerCount(): int { return $this->followers()->count(); }
}
