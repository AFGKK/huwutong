<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperChannel
 */
class Channel extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'description', 'type', 'created_by', 'icon', 'avatar', 'is_active', 'last_message_at'];
    protected $casts = ['is_active' => 'boolean', 'last_message_at' => 'datetime'];

    public function category(): BelongsTo { return $this->belongsTo(ChannelCategory::class, 'category_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function members(): HasMany { return $this->hasMany(ChannelMember::class); }
    public function messages(): HasMany { return $this->hasMany(ChannelMessage::class); }
    public function latestMessage(): BelongsTo { return $this->belongsTo(ChannelMessage::class, 'last_message_id'); }
}
