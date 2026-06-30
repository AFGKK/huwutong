<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 数据泄露通知
 */
class DataBreachNotification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'status', 'severity', 'detected_at', 'contained_at',
        'description', 'root_cause', 'impact_assessment',
        'affected_data_categories', 'affected_users_count',
        'containment_actions',
        'notified_supervisory_authority', 'authority_notified_at',
        'authority_response',
        'notified_affected_users', 'users_notified_at',
        'remediation_plan', 'remediated_at', 'evidence_refs',
        'reported_by', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'contained_at' => 'datetime',
            'affected_data_categories' => 'array',
            'notified_supervisory_authority' => 'boolean',
            'authority_notified_at' => 'datetime',
            'notified_affected_users' => 'boolean',
            'users_notified_at' => 'datetime',
            'remediated_at' => 'datetime',
            'evidence_refs' => 'array',
        ];
    }

    const SEVERITIES = ['critical', 'high', 'medium', 'low'];
    const STATUSES = ['detected', 'assessing', 'reported', 'resolved', 'closed'];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
