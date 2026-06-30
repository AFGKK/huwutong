<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerFeedback extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'user_id', 'tenant_id',
        'type', 'rating', 'message', 'subject',
        'page_url', 'page_title', 'component_path',
        'user_agent', 'browser', 'os', 'screen_resolution', 'language', 'ip_address',
        'screenshots', 'attachments', 'annotations',
        'status', 'priority',
        'assigned_to', 'assigned_at',
        'admin_reply', 'replied_at', 'replied_by',
        'resolved_at', 'metadata',
    ];

    protected $appends = ['vote_count'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'screenshots' => 'array',
            'attachments' => 'array',
            'annotations' => 'array',
            'metadata' => 'array',
            'assigned_at' => 'datetime',
            'replied_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    const TYPES = ['general', 'bug', 'feature_request', 'performance', 'ui_ux', 'other'];
    const STATUSES = ['new', 'under_review', 'acknowledged', 'in_progress', 'resolved', 'closed', 'wont_fix'];
    const PRIORITIES = ['low', 'normal', 'high', 'critical'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(FeedbackTag::class, 'customer_feedback_tags', 'feedback_id', 'tag_id');
    }

    public function getVoteCountAttribute(): int
    {
        return $this->votes_count ?? $this->votes()->sum('vote');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeatureVote::class, 'feedback_id');
    }

    public function upvotes(): HasMany
    {
        return $this->hasMany(FeatureVote::class, 'feedback_id')->where('vote', 1);
    }

    public function downvotes(): HasMany
    {
        return $this->hasMany(FeatureVote::class, 'feedback_id')->where('vote', -1);
    }
}
