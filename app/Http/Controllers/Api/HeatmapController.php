<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HeatmapLayer;
use App\Services\HeatmapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HeatmapController extends Controller
{
    public function __construct(
        protected HeatmapService $service
    ) {}

    // ─── 图层管理 ───

    public function layers(Request $request)
    {
        return ApiResponse::success($this->service->listLayers($request->user()->tenant_id));
    }

    public function storeLayer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'slug' => 'required|string|max:200|unique:heatmap_layers,slug',
            'data_source' => 'required|string|in:license_activations,product_usage,api_calls,revenue',
            'type' => 'nullable|string|in:heatmap_scatter,country_choropleth,region_bubble',
            'config' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createLayer($data), 201);
    }

    public function updateLayer(Request $request, HeatmapLayer $heatmapLayer)
    {
        return ApiResponse::success($this->service->updateLayer($heatmapLayer, $request->all()));
    }

    public function deleteLayer(HeatmapLayer $heatmapLayer)
    {
        $this->service->deleteLayer($heatmapLayer);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 热力图数据 ───

    public function data(Request $request)
    {
        return ApiResponse::success(
            $this->service->getMultiLayerData(
                $request->user()->tenant_id,
                $request->only(['days', 'layers'])
            )
        );
    }

    // ─── 国家钻取 ───

    public function countryDetail(Request $request, string $countryCode)
    {
        return ApiResponse::success(
            $this->service->getCountryDetail(
                $request->user()->tenant_id,
                $countryCode,
                $request->only(['days'])
            )
        );
    }

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboardStats($request->user()->tenant_id)
        );
    }
}
