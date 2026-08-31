<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ImportMappingTemplate;
use App\Models\ImportTask;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DataImportController extends Controller
{
    public function __construct(
        protected ImportService $importService
    ) {}

    // ─── 元数据 ───

    public function entityTypes()
    {
        return ApiResponse::success($this->importService->getEntityTypes());
    }

    public function entityFields(Request $request, string $entityType)
    {
        return ApiResponse::success($this->importService->getEntityFields($entityType));
    }

    public function generateTemplate(Request $request, string $entityType)
    {
        return ApiResponse::success($this->importService->generateTemplate($entityType));
    }

    // ─── 上传与解析 ───

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:51200',
            'entity_type' => 'required|string|in:licenses,customers,subscriptions,products,tickets',
            'options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $task = $this->importService->upload(
            $request->file('file'),
            $request->input('entity_type'),
            $request->input('options', [])
        );

        return ApiResponse::success($task, 201);
    }

    public function parse(ImportTask $importTask)
    {
        if (!in_array($importTask->status, ['uploaded'])) {
            return ApiResponse::success(['error' => __("app.data_import.msg_30b3bd7a")], 422);
        }

        $task = $this->importService->parseFile($importTask);
        return ApiResponse::success($task->load('mappings'));
    }

    // ─── 映射管理 ───

    public function updateMappings(Request $request, ImportTask $importTask)
    {
        $validator = Validator::make($request->all(), [
            'mappings' => 'required|array',
            'mappings.*.id' => 'required|integer',
            'mappings.*.target_field' => 'nullable|string',
            'mappings.*.default_value' => 'nullable',
            'mappings.*.is_required' => 'nullable|boolean',
            'mappings.*.is_identifier' => 'nullable|boolean',
            'mappings.*.transform_rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $task = $this->importService->updateMappings($importTask, $request->input('mappings'));
        return ApiResponse::success($task->load('mappings'));
    }

    // ─── 验证 ───

    public function validate(ImportTask $importTask)
    {
        if (!in_array($importTask->status, ['preview'])) {
            return ApiResponse::success(['error' => __("app.data_import.msg_2fa26ace")], 422);
        }

        $task = $this->importService->validate($importTask);
        return ApiResponse::success($task);
    }

    // ─── 执行导入 ───

    public function execute(ImportTask $importTask)
    {
        if (!in_array($importTask->status, ['validated', 'preview'])) {
            return ApiResponse::success(['error' => __("app.data_import.msg_b9d8f601")], 422);
        }

        $task = $this->importService->execute($importTask);
        return ApiResponse::success($task);
    }

    // ─── 任务管理 ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->importService->getTasks(
                $request->user()->id,
                $request->input('entity_type'),
                $request->input('status')
            )
        );
    }

    public function show(ImportTask $importTask)
    {
        return ApiResponse::success($importTask->load(['mappings', 'logs' => function ($q) {
            $q->orderBy('row_number')->limit(200);
        }]));
    }

    public function logs(Request $request, ImportTask $importTask)
    {
        return ApiResponse::success(
            $this->importService->getLogs(
                $importTask,
                $request->input('level'),
                $request->input('page', 1),
                $request->input('per_page', 50)
            )
        );
    }

    public function cancel(ImportTask $importTask)
    {
        $this->importService->cancelTask($importTask);
        return ApiResponse::success(['cancelled' => true]);
    }

    public function destroy(ImportTask $importTask)
    {
        $this->importService->deleteTask($importTask);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 模板管理 ───

    public function mappingTemplates(Request $request)
    {
        return ApiResponse::success(
            $this->importService->getMappingTemplates($request->input('entity_type'))
        );
    }

    public function storeMappingTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'entity_type' => 'required|string|in:licenses,customers,subscriptions,products,tickets',
            'mappings' => 'required|array',
            'default_options' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['user_id'] = $request->user()->id;

        return ApiResponse::success($this->importService->createMappingTemplate($data), 201);
    }

    public function destroyMappingTemplate(int $id)
    {
        $this->importService->deleteMappingTemplate($id);
        return ApiResponse::success(['deleted' => true]);
    }

    public function applyMappingTemplate(Request $request, ImportTask $importTask)
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|integer|exists:import_mapping_templates,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $task = $this->importService->applyMappingTemplate(
            $importTask,
            $request->input('template_id')
        );

        return ApiResponse::success($task);
    }
}
