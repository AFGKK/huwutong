<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaSubmission extends Model
{
    protected $fillable = ['account_id', 'user_id', 'title', 'content', 'cover_image', 'summary', 'status', 'reviewer_id', 'reviewed_at', 'reject_reason'];
    protected $casts = ['reviewed_at' => 'datetime'];
    protected $table = 'oa_submissions';

    public function account(): BelongsTo { return $this->belongsTo(OfficialAccount::class, 'account_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
