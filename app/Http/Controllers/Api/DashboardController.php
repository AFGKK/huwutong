<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    // ─── 仪表盘 CRUD ───

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->dashboardService->getDashboards($userId, $tenantId));
    }

    public function overview(Request $request)
    {
        $userId = $request->user()->id;
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->dashboardService->getDashboardOverview($userId, $tenantId));
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->dashboardService->getDashboardWithData($id));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'layout_type' => 'nullable|string|in:grid,free,flex',
            'columns' => 'nullable|integer|min:1|max:24',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->dashboardService->createDashboard($data), 201);
    }

    public function update(Request $request, Dashboard $dashboard)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'layout_type' => 'nullable|string|in:grid,free,flex',
            'layout_config' => 'nullable|array',
            'columns' => 'nullable|integer|min:1|max:24',
            'is_shared' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->dashboardService->updateDashboard($dashboard, $request->all()));
    }

    public function destroy(Dashboard $dashboard)
    {
        $this->dashboardService->deleteDashboard($dashboard);
        return ApiResponse::success(['deleted' => true]);
    }

    public function setDefault(Dashboard $dashboard)
    {
        return ApiResponse::success($this->dashboardService->setDefault($dashboard));
    }

    public function duplicate(Dashboard $dashboard)
    {
        return ApiResponse::success($this->dashboardService->duplicateDashboard($dashboard));
    }

    // ─── Widget CRUD ───

    public function storeWidget(Request $request, Dashboard $dashboard)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:stat,chart,list,metric,table,iframe,html,alert,report',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'layout' => 'nullable|array',
            'data_source' => 'nullable|array',
            'visual_options' => 'nullable|array',
            'template_id' => 'nullable|integer|exists:dashboard_widget_templates,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $templateId = $request->input('template_id');
        if ($templateId) {
            $widget = $this->dashboardService->createWidgetFromTemplate(
                $dashboard->id,
                $templateId,
                $request->except('template_id')
            );
        } else {
            $widget = $this->dashboardService->addWidget($dashboard->id, $request->all());
        }

        return ApiResponse::success($widget, 201);
    }

    public function updateWidget(Request $request, DashboardWidget $widget)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:stat,chart,list,metric,table,iframe,html,alert,report',
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'layout' => 'nullable|array',
            'data_source' => 'nullable|array',
            'visual_options' => 'nullable|array',
            'is_visible' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->dashboardService->updateWidget($widget, $request->all()));
    }

    public function destroyWidget(DashboardWidget $widget)
    {
        $widgetId = $widget->id;
        $this->dashboardService->deleteWidget($widget);
        return ApiResponse::success(['deleted' => true]);
    }

    public function reorderWidgets(Request $request, Dashboard $dashboard)
    {
        $validator = Validator::make($request->all(), [
            'order' => 'required|array',
            'order.*.id' => 'required|integer',
            'order.*.sort_order' => 'required|integer',
            'order.*.layout' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $this->dashboardService->reorderWidgets($dashboard->id, $request->input('order'));
        return ApiResponse::success(['updated' => true]);
    }

    public function getWidgetData(Request $request, DashboardWidget $widget)
    {
        return ApiResponse::success($this->dashboardService->getWidgetData($widget));
    }

    public function refreshWidgetData(Request $request, DashboardWidget $widget)
    {
        return ApiResponse::success($this->dashboardService->refreshWidgetData($widget));
    }

    // ─── Widget 模板 ───

    public function widgetTemplates(Request $request)
    {
        return ApiResponse::success(
            $this->dashboardService->getWidgetTemplates($request->input('category'))
        );
    }
}
