<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LicenseBatchGeneration;
use App\Models\LicenseTemplate;
use App\Services\LicenseTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LicenseTemplateExtController extends Controller
{
    public function __construct(
        protected LicenseTemplateService $licenseTemplateService
    ) {}

    // ─── 模板变量 ───

    public function variables(int $templateId)
    {
        $template = LicenseTemplate::findOrFail($templateId);
        return ApiResponse::success($this->licenseTemplateService->getVariables($template->id));
    }

    public function saveVariables(Request $request, int $templateId)
    {
        $template = LicenseTemplate::findOrFail($templateId);
        $validator = Validator::make($request->all(), [
            'variables' => 'required|array',
            'variables.*.key' => 'required|string|max:80',
            'variables.*.label' => 'nullable|string|max:200',
            'variables.*.variable_type' => 'nullable|string|in:string,number,date,boolean,select',
            'variables.*.is_required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->first());
        }

        $this->licenseTemplateService->saveVariables($template->id, $request->input('variables'));
        return ApiResponse::success(['saved' => true], __("app.license_template_ext.msg_cd5f28f7"));
    }

    // ─── 字段映射 ───

    public function saveFieldMappings(Request $request, int $templateId)
    {
        LicenseTemplate::findOrFail($templateId);
        $validator = Validator::make($request->all(), [
            'mappings' => 'required|array',
            'mappings.*.template_field' => 'required|string',
            'mappings.*.license_field' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->first());
        }

        $this->licenseTemplateService->saveFieldMappings($templateId, $request->input('mappings'));
        return ApiResponse::success(['saved' => true], __("app.license_template_ext.msg_a9480901"));
    }

    // ─── 批量生成 ───

    public function batchGenerate(Request $request, int $templateId)
    {
        $template = LicenseTemplate::findOrFail($templateId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'rows' => 'required|array|min:1|max:500',
            'rows.*' => 'required|array',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->first());
        }

        $batch = $this->licenseTemplateService->batchGenerate(
            $template,
            $request->user()->id,
            $request->input('name'),
            $request->input('rows'),
            $request->only(['customer_id', 'status'])
        );

        return ApiResponse::created($batch, __("app.license_template_ext.msg_7145b4b8"));
    }

    public function batchTasks(Request $request)
    {
        $paginated = $this->licenseTemplateService->getBatchTasks(
            $request->user()->tenant_id,
            $request->only(['status', 'page', 'per_page'])
        );

        return ApiResponse::success($paginated['data'] ?? $paginated, __("app.license_template_ext.msg_d72b49e1"));
    }

    public function batchTaskShow(int $id)
    {
        return ApiResponse::success($this->licenseTemplateService->getBatchTask($id));
    }

    public function batchTaskDestroy(int $id)
    {
        $batch = LicenseBatchGeneration::findOrFail($id);
        $batch->items()->delete();
        $batch->delete();
        return ApiResponse::success(null, __("app.license_template_ext.msg_b2ae04aa"));
    }

    // ─── 预览 ───

    public function preview(Request $request, int $templateId)
    {
        $template = LicenseTemplate::findOrFail($templateId);

        $validator = Validator::make($request->all(), [
            'rows' => 'required|array|min:1|max:10',
            'rows.*' => 'required|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->first());
        }

        return ApiResponse::success(
            $this->licenseTemplateService->previewGenerate(
                $template,
                $request->input('rows'),
                $request->except('rows')
            )
        );
    }

    // ─── 模板详情（含变量和映射） ───

    public function showWithExtras(int $id)
    {
        return ApiResponse::success($this->licenseTemplateService->getTemplateWithExtras($id));
    }
}
