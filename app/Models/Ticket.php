<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'user_id', 'category_id',
        'assigned_to', 'subject', 'description',
        'priority', 'status', 'source',
        'tags', 'metadata',
        'sla_minutes', 'sla_due_at',
        'first_response_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'sla_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function publicReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->where('is_internal', false);
    }

    public function slaEvents(): HasMany
    {
        return $this->hasMany(TicketSlaEvent::class);
    }

    public function satisfaction(): HasOne
    {
        return $this->hasOne(TicketSatisfaction::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function replyCount(): int
    {
        return $this->replies()->count();
    }

    public function assignTo(User $user): void
    {
        $this->update(['assigned_to' => $user->id]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function recordFirstResponse(): void
    {
        if (empty($this->first_response_at)) {
            $this->update(['first_response_at' => now()]);
        }
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_due_at && now()->greaterThan($this->sla_due_at) && !$this->isClosed();
    }

    public function getResponseTimeMinutes(): ?int
    {
        if (!$this->first_response_at) return null;
        return (int) $this->created_at->diffInMinutes($this->first_response_at);
    }

    public function getResolutionTimeMinutes(): ?int
    {
        if (!$this->resolved_at) return null;
        return (int) $this->created_at->diffInMinutes($this->resolved_at);
    }
}
