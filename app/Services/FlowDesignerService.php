<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowDesign;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 低代码工作流设计器服务 (M3-82)
 *
 * 提供可视化工作流设计器的核心逻辑：
 * - 工作流设计 CRUD
 * - 节点/连线管理
 * - 导入/导出到 WorkflowEngine
 */
class FlowDesignerService
{
    // ═══════ 设计 CRUD ═══════

    public function listDesigns(int $tenantId, array $filters = [], int $perPage = 20): array
    {
        $query = WorkflowDesign::withCount('nodes')->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['category'])) $query->where('category', $filters['category']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        return $query->orderBy('updated_at', 'desc')
            ->paginate($perPage)
            ->toArray();
    }

    public function createDesign(array $data): WorkflowDesign
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        return WorkflowDesign::create($data);
    }

    public function updateDesign(WorkflowDesign $design, array $data): WorkflowDesign
    {
        $design->update($data);
        return $design->fresh();
    }

    public function deleteDesign(WorkflowDesign $design): void
    {
        DB::transaction(function () use ($design) {
            $design->nodes()->delete();
            $design->edges()->delete();
            $design->delete();
        });
    }

    public function getDesignWithGraph(int $id): ?WorkflowDesign
    {
        return WorkflowDesign::with(['nodes', 'edges'])->find($id);
    }

    // ═══════ 节点管理 ═══════

    public function addNode(int $designId, array $data): WorkflowNode
    {
        return WorkflowNode::create(array_merge($data, ['design_id' => $designId]));
    }

    public function updateNode(WorkflowNode $node, array $data): WorkflowNode
    {
        $node->update($data);
        return $node->fresh();
    }

    public function deleteNode(WorkflowNode $node): void
    {
        // 移除相关连线
        WorkflowEdge::where('design_id', $node->design_id)
            ->where(function ($q) use ($node) {
                $q->where('source_node', $node->node_id)
                  ->orWhere('target_node', $node->node_id);
            })->delete();
        $node->delete();
    }

    // ═══════ 连线管理 ═══════

    public function addEdge(int $designId, array $data): WorkflowEdge
    {
        return WorkflowEdge::create(array_merge($data, ['design_id' => $designId]));
    }

    public function updateEdge(WorkflowEdge $edge, array $data): WorkflowEdge
    {
        $edge->update($data);
        return $edge->fresh();
    }

    public function deleteEdge(WorkflowEdge $edge): void
    {
        $edge->delete();
    }

    // ═══════ 批量保存（前端拖拽完成后的全量同步） ═══════

    public function saveFullGraph(int $designId, array $graphData): array
    {
        DB::transaction(function () use ($designId, $graphData) {
            // 更新画布配置
            if (isset($graphData['canvas_config'])) {
                WorkflowDesign::where('id', $designId)->update([
                    'canvas_config' => $graphData['canvas_config'],
                ]);
            }

            // 替换所有节点
            if (isset($graphData['nodes'])) {
                WorkflowNode::where('design_id', $designId)->delete();
                foreach ($graphData['nodes'] as $node) {
                    $node['design_id'] = $designId;
                    WorkflowNode::create($node);
                }
            }

            // 替换所有连线
            if (isset($graphData['edges'])) {
                WorkflowEdge::where('design_id', $designId)->delete();
                foreach ($graphData['edges'] as $edge) {
                    $edge['design_id'] = $designId;
                    WorkflowEdge::create($edge);
                }
            }
        });

        return $this->getDesignWithGraph($designId)->toArray();
    }

    // ═══════ 导出到 WorkflowEngine ═══════

    public function exportToWorkflowDefinition(int $designId): ?array
    {
        $design = $this->getDesignWithGraph($designId);
        if (!$design) return null;

        $nodes = $design->nodes->keyBy('node_id');
        $edges = $design->edges;

        // 构建步骤定义
        $steps = [];
        $startNodes = $nodes->where('type', 'trigger')->values();

        // 从触发器开始，沿着连线构建步骤链
        foreach ($startNodes as $startNode) {
            $stepChain = $this->buildStepChain($startNode->node_id, $nodes, $edges, $design->id);
            $steps = array_merge($steps, $stepChain);
        }

        // 创建或更新 WorkflowDefinition
        $definition = WorkflowDefinition::updateOrCreate(
            ['name' => $design->slug],
            [
                'description' => $design->description,
                'steps_definition' => $steps,
                'is_active' => $design->is_active && $design->status === 'published',
            ]
        );

        // 标记设计为已导出
        $design->update(['metadata' => array_merge($design->metadata ?? [], [
            'exported_at' => now()->toIso8601String(),
            'definition_id' => $definition->id,
        ])]);

        return $definition->toArray();
    }

    /**
     * 构建步骤链：从 startNodeId 沿 edges 遍历
     */
    protected function buildStepChain(string $startNodeId, $nodes, $edges, int $designId): array
    {
        $steps = [];
        $visited = [];
        $queue = [$startNodeId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            if (in_array($currentId, $visited)) continue;
            $visited[] = $currentId;

            $node = $nodes->get($currentId);
            if (!$node || $node->type === 'end') continue;

            $stepConfig = $this->nodeToStepConfig($node);

            // 找出由此节点出发的边
            $outgoingEdges = $edges->where('source_node', $currentId)->sortBy('sort_order');

            $nextNodes = [];
            foreach ($outgoingEdges as $edge) {
                $targetNode = $nodes->get($edge->target_node);
                if ($targetNode) {
                    $nextNodes[] = [
                        'node_id' => $edge->target_node,
                        'edge' => $edge,
                    ];
                }
            }

            // 如果是条件节点，添加分支信息
            if ($node->type === 'condition') {
                $branches = [];
                foreach ($nextNodes as $next) {
                    $branches[] = [
                        'condition' => $next['edge']->condition_type ?? 'success',
                        'target_step' => $nodes->get($next['node_id'])?->label ?? $next['node_id'],
                        'condition_config' => $next['edge']->condition_config,
                    ];
                }
                $stepConfig['branches'] = $branches;
            }

            $steps[] = $stepConfig;

            // 入队下一层节点
            foreach ($nextNodes as $next) {
                $queue[] = $next['node_id'];
            }
        }

        return $steps;
    }

    /**
     * 将画布节点转换为 WorkflowDefinition 步骤配置
     */
    protected function nodeToStepConfig($node): array
    {
        $step = [
            'name' => $node->node_id,
            'label' => $node->label,
            'type' => $node->type,
            'input' => $node->input_schema ?? [],
            'output' => $node->output_schema ?? [],
        ];

        // 根据节点类型添加特定配置
        switch ($node->type) {
            case 'trigger':
                $step['trigger_config'] = $node->config ?? ['event' => 'manual'];
                break;
            case 'condition':
                $step['condition_config'] = $node->config ?? ['field' => 'status', 'operator' => 'eq', 'value' => 'active'];
                break;
            case 'action':
                $step['action_config'] = $node->config ?? ['type' => 'webhook', 'url' => ''];
                break;
            case 'approval':
                $step['approval_config'] = $node->config ?? ['type' => 'single', 'assignee' => 'admin'];
                break;
            case 'webhook':
                $step['webhook_config'] = $node->config ?? ['url' => '', 'method' => 'POST'];
                break;
        }

        return $step;
    }

    // ═══════ 元数据 ═══════

    public function getNodePalette(): array
    {
        return [
            ['type' => 'trigger', 'label' => '触发器', 'icon' => 'VideoPlay', 'color' => '#409eff',
             'description' => '工作流触发条件（事件/定时/手动）'],
            ['type' => 'condition', 'label' => '条件判断', 'icon' => 'QuestionFilled', 'color' => '#e6a23c',
             'description' => '条件分支路由'],
            ['type' => 'action', 'label' => '执行动作', 'icon' => 'SetUp', 'color' => '#67c23a',
             'description' => '执行具体操作（更新License/发邮件等）'],
            ['type' => 'approval', 'label' => '审批节点', 'icon' => 'EditPen', 'color' => '#9b59b6',
             'description' => '人工审批步骤'],
            ['type' => 'webhook', 'label' => 'Webhook', 'icon' => 'Connection', 'color' => '#00adef',
             'description' => '发送HTTP请求到外部系统'],
            ['type' => 'end', 'label' => '结束节点', 'icon' => 'CircleClose', 'color' => '#909399',
             'description' => '工作流终止点'],
        ];
    }

    public function getStats(int $tenantId): array
    {
        $total = WorkflowDesign::where('tenant_id', $tenantId)->count();
        $published = WorkflowDesign::where('tenant_id', $tenantId)->where('status', 'published')->count();
        $drafts = WorkflowDesign::where('tenant_id', $tenantId)->where('status', 'draft')->count();

        $byCategory = WorkflowDesign::where('tenant_id', $tenantId)
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category')
            ->toArray();

        return [
            'total_designs' => $total,
            'published' => $published,
            'drafts' => $drafts,
            'by_category' => $byCategory,
        ];
    }
}
