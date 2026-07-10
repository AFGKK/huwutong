<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperWorkflowDesign
 */
class WorkflowDesign extends Model
{
    use HasFactory;

    protected $table = 'workflow_designs';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'category',
        'canvas_config', 'metadata', 'is_active', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'canvas_config' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    const CATEGORIES = [
        'general' => '通用',
        'approval' => '审批',
        'license' => 'License',
        'billing' => '计费',
        'notification' => '通知',
    ];

    const STATUSES = ['draft', 'published', 'archived'];

    const NODE_TYPES = [
        'trigger' => '触发器',
        'condition' => '条件判断',
        'action' => '执行动作',
        'approval' => '审批节点',
        'webhook' => 'Webhook',
        'end' => '结束节点',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function nodes(): HasMany { return $this->hasMany(WorkflowNode::class, 'design_id'); }
    public function edges(): HasMany { return $this->hasMany(WorkflowEdge::class, 'design_id'); }
}
