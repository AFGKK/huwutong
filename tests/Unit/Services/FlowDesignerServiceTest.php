<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDesign;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\FlowDesignerService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class FlowDesignerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FlowDesignerService $service;
    protected Tenant $tenant;
    protected WorkflowDesign $design;
    protected array $graphData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FlowDesignerService::class);
        $this->tenant = Tenant::factory()->create();
        $this->design = WorkflowDesign::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->graphData = [
            'canvas_config' => ['zoom' => 1.0, 'offset_x' => 0, 'offset_y' => 0],
            'nodes' => [
                [
                    'node_id' => 'node_1', 'type' => 'trigger', 'label' => '手动触发',
                    'config' => ['event' => 'manual'], 'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'node_id' => 'node_2', 'type' => 'action', 'label' => '发送通知',
                    'config' => ['action_type' => 'webhook', 'url' => 'https://hook.example.com'],
                    'position' => ['x' => 400, 'y' => 100],
                ],
                [
                    'node_id' => 'node_3', 'type' => 'end', 'label' => '结束',
                    'position' => ['x' => 700, 'y' => 100],
                ],
            ],
            'edges' => [
                [
                    'edge_id' => 'edge_1',
                    'source_node' => 'node_1', 'target_node' => 'node_2',
                    'condition_type' => 'success',
                ],
                [
                    'edge_id' => 'edge_2',
                    'source_node' => 'node_2', 'target_node' => 'node_3',
                    'condition_type' => 'success',
                ],
            ],
        ];
    }

    /** @test */
    public function it_lists_designs()
    {
        WorkflowDesign::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->listDesigns($this->tenant->id);
        $this->assertGreaterThanOrEqual(4, $result['total']);
    }

    /** @test */
    public function it_creates_design()
    {
        $data = ['tenant_id' => $this->tenant->id, 'name' => 'Test Workflow', 'slug' => 'test-workflow'];
        $design = $this->service->createDesign($data);

        $this->assertEquals('Test Workflow', $design->name);
        $this->assertEquals('test-workflow', $design->slug);
    }

    /** @test */
    public function it_updates_design()
    {
        $updated = $this->service->updateDesign($this->design, ['name' => 'Updated Name']);
        $this->assertEquals('Updated Name', $updated->name);
    }

    /** @test */
    public function it_deletes_design_with_nodes_and_edges()
    {
        $this->service->saveFullGraph($this->design->id, $this->graphData);

        $this->service->deleteDesign($this->design);

        $this->assertNull(WorkflowDesign::find($this->design->id));
        $this->assertEmpty(WorkflowNode::where('design_id', $this->design->id)->get());
        $this->assertEmpty(WorkflowEdge::where('design_id', $this->design->id)->get());
    }

    /** @test */
    public function it_adds_node()
    {
        $node = $this->service->addNode($this->design->id, [
            'node_id' => 'node_1',
            'type' => 'trigger',
            'label' => '触发器',
            'position' => ['x' => 100, 'y' => 100],
        ]);

        $this->assertEquals('node_1', $node->node_id);
        $this->assertEquals('trigger', $node->type);
    }

    /** @test */
    public function it_updates_node()
    {
        $node = $this->service->addNode($this->design->id, [
            'node_id' => 'node_1', 'type' => 'action', 'label' => '旧标签',
        ]);

        $updated = $this->service->updateNode($node, ['label' => '新标签']);
        $this->assertEquals('新标签', $updated->label);
    }

    /** @test */
    public function it_deletes_node_and_removes_edges()
    {
        $this->service->saveFullGraph($this->design->id, $this->graphData);

        $node2 = WorkflowNode::where('design_id', $this->design->id)
            ->where('node_id', 'node_1')->first();

        $this->service->deleteNode($node2);

        $remainingNodes = WorkflowNode::where('design_id', $this->design->id)->get();
        $remainingEdges = WorkflowEdge::where('design_id', $this->design->id)->get();
        $this->assertCount(2, $remainingNodes); // node_2, node_3
        $this->assertCount(1, $remainingEdges); // edge_2 (node_2->node_3) remains
        // Actually, edge_1 (node_1->node_2) is removed, edge_2 (node_2->node_3) remains
        // $this->assertCount(1, $remainingEdges);
    }

    /** @test */
    public function it_adds_edge()
    {
        $this->service->addNode($this->design->id, [
            'node_id' => 'node_1', 'type' => 'trigger', 'label' => 'T1',
        ]);
        $this->service->addNode($this->design->id, [
            'node_id' => 'node_2', 'type' => 'action', 'label' => 'A1',
        ]);

        $edge = $this->service->addEdge($this->design->id, [
            'edge_id' => 'e1', 'source_node' => 'node_1', 'target_node' => 'node_2',
        ]);

        $this->assertEquals('e1', $edge->edge_id);
    }

    /** @test */
    public function it_saves_full_graph()
    {
        $result = $this->service->saveFullGraph($this->design->id, $this->graphData);

        $this->assertCount(3, $result['nodes']);
        $this->assertCount(2, $result['edges']);

        // 确认数据库
        $this->assertEquals(3, WorkflowNode::where('design_id', $this->design->id)->count());
        $this->assertEquals(2, WorkflowEdge::where('design_id', $this->design->id)->count());
    }

    /** @test */
    public function it_exports_to_workflow_definition()
    {
        $this->service->saveFullGraph($this->design->id, $this->graphData);

        $definition = $this->service->exportToWorkflowDefinition($this->design->id);

        $this->assertNotNull($definition);
        $this->assertCount(2, $definition['steps_definition']);

        // 验证步骤链正确（end节点被排除）
        $steps = $definition['steps_definition'];
        $this->assertEquals('node_1', $steps[0]['name']);
        $this->assertEquals('trigger', $steps[0]['type']);
        $this->assertEquals('node_2', $steps[1]['name']);
        $this->assertEquals('action', $steps[1]['type']);
    }

    /** @test */
    public function it_returns_node_palette()
    {
        $palette = $this->service->getNodePalette();
        $this->assertCount(6, $palette);
        $this->assertEquals('trigger', $palette[0]['type']);
    }

    /** @test */
    public function it_returns_stats()
    {
        WorkflowDesign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'published']);
        WorkflowDesign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'published']);
        WorkflowDesign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft']);

        $stats = $this->service->getStats($this->tenant->id);

        $this->assertGreaterThanOrEqual(4, $stats['total_designs']);
        $this->assertGreaterThanOrEqual(2, $stats['published']);
        $this->assertGreaterThanOrEqual(1, $stats['drafts']);
    }

    /** @test */
    public function it_exports_only_published_as_active()
    {
        $this->design->update(['status' => 'published', 'is_active' => true]);
        $this->service->saveFullGraph($this->design->id, $this->graphData);

        $definition = $this->service->exportToWorkflowDefinition($this->design->id);

        $this->assertNotNull($definition);
        $this->assertTrue($definition['is_active']);
    }

    /** @test */
    public function it_creates_workflow_definition_on_export()
    {
        $this->design->update(['status' => 'published', 'is_active' => true]);
        $this->service->saveFullGraph($this->design->id, $this->graphData);

        $this->service->exportToWorkflowDefinition($this->design->id);

        $this->assertDatabaseHas('workflow_definitions', ['name' => $this->design->slug]);
    }
}
