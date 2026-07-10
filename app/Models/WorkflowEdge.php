<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperWorkflowEdge
 */
class WorkflowEdge extends Model
{
    use HasFactory;

    protected $table = 'workflow_edges';

    protected $fillable = [
        'design_id', 'edge_id', 'source_node', 'target_node',
        'source_handle', 'target_handle', 'label', 'condition_type',
        'condition_config', 'line_style', 'color', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'condition_config' => 'array',
        ];
    }

    const CONDITION_TYPES = ['success', 'failure', 'conditional'];
    const LINE_STYLES = ['solid', 'dashed', 'dotted'];

    public function design(): BelongsTo { return $this->belongsTo(WorkflowDesign::class, 'design_id'); }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'source_node', 'node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'target_node', 'node_id');
    }
}
