<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\WorkflowDesign;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\FlowDesignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlowDesignerController extends Controller
{
    public function __construct(
        protected FlowDesignerService $service
    ) {}

    // ─── 设计 CRUD ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->service->listDesigns(
                $request->user()->tenant_id,
                $request->only(['status', 'category', 'search']),
                $request->input('per_page', 20)
            )
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['created_by'] = $request->user()->id;

        return ApiResponse::success($this->service->createDesign($data), 201);
    }

    public function show(WorkflowDesign $workflowDesign)
    {
        $design = $this->service->getDesignWithGraph($workflowDesign->id);
        return ApiResponse::success($design);
    }

    public function update(Request $request, WorkflowDesign $workflowDesign)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:draft,published,archived',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->service->updateDesign($workflowDesign, $request->all()));
    }

    public function destroy(WorkflowDesign $workflowDesign)
    {
        $this->service->deleteDesign($workflowDesign);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 节点管理 ───

    public function addNode(Request $request, WorkflowDesign $workflowDesign)
    {
        $validator = Validator::make($request->all(), [
            'node_id' => 'required|string|max:80',
            'type' => 'required|string|in:trigger,condition,action,approval,webhook,end',
            'label' => 'required|string|max:200',
            'config' => 'nullable|array',
            'position' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->addNode($workflowDesign->id, $request->all()),
            201
        );
    }

    public function updateNode(Request $request, WorkflowDesign $workflowDesign, WorkflowNode $workflowNode)
    {
        return ApiResponse::success(
            $this->service->updateNode($workflowNode, $request->all())
        );
    }

    public function deleteNode(WorkflowDesign $workflowDesign, WorkflowNode $workflowNode)
    {
        $this->service->deleteNode($workflowNode);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 连线管理 ───

    public function addEdge(Request $request, WorkflowDesign $workflowDesign)
    {
        $validator = Validator::make($request->all(), [
            'edge_id' => 'required|string|max:80',
            'source_node' => 'required|string|max:80',
            'target_node' => 'required|string|max:80',
            'condition_type' => 'nullable|string|in:success,failure,conditional',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->addEdge($workflowDesign->id, $request->all()),
            201
        );
    }

    public function deleteEdge(WorkflowDesign $workflowDesign, WorkflowEdge $workflowEdge)
    {
        $this->service->deleteEdge($workflowEdge);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 批量保存 ───

    public function saveGraph(Request $request, WorkflowDesign $workflowDesign)
    {
        $validator = Validator::make($request->all(), [
            'nodes' => 'nullable|array',
            'edges' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->saveFullGraph($workflowDesign->id, $request->all())
        );
    }

    // ─── 导出 ───

    public function export(WorkflowDesign $workflowDesign)
    {
        $definition = $this->service->exportToWorkflowDefinition($workflowDesign->id);

        if (!$definition) {
            return ApiResponse::success(['error' => __("app.flow_designer.msg_dd51ab50")], 400);
        }

        return ApiResponse::success($definition);
    }

    // ─── 元数据 ───

    public function nodePalette()
    {
        return ApiResponse::success($this->service->getNodePalette());
    }

    public function stats(Request $request)
    {
        return ApiResponse::success(
            $this->service->getStats($request->user()->tenant_id)
        );
    }

    public function categories()
    {
        return ApiResponse::success(WorkflowDesign::CATEGORIES);
    }
}
