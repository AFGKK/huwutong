<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiConnection;
use App\Models\BiDataset;
use App\Services\BiExport\BiExportService;
use Illuminate\Http\Request;

class BiExportController extends Controller
{
    public function __construct(protected BiExportService $biExport) {}

    /**
     * 连接列表
     */
    public function connections()
    {
        $connections = BiConnection::withCount('datasets')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $connections]);
    }

    /**
     * 创建连接
     */
    public function storeConnection(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'platform' => 'required|in:csv',
            'config' => 'required|array',
        ]);

        $conn = BiConnection::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
            'status' => 'disconnected',
        ]));

        // 测试连接
        try {
            $this->biExport->testConnection($conn);
        } catch (\Exception $e) {
            // 连接测试失败不阻止创建
        }

        return response()->json(['success' => true, 'data' => $conn], 201);
    }

    /**
     * 更新连接
     */
    public function updateConnection(Request $request, BiConnection $biConnection)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'config' => 'sometimes|array',
        ]);

        $biConnection->update($validated);
        return response()->json(['success' => true, 'data' => $biConnection]);
    }

    /**
     * 删除连接
     */
    public function destroyConnection(BiConnection $biConnection)
    {
        $biConnection->datasets()->each(function ($ds) {
            $ds->syncLogs()->delete();
            $ds->delete();
        });
        $biConnection->delete();
        return response()->json(['success' => true]);
    }

    /**
     * 测试连接
     */
    public function testConnection(BiConnection $biConnection)
    {
        $result = $this->biExport->testConnection($biConnection);
        return response()->json([
            'success' => true,
            'data' => ['connected' => $result, 'status' => $biConnection->fresh()->status],
        ]);
    }

    /**
     * 数据集列表
     */
    public function datasets(BiConnection $biConnection)
    {
        $datasets = $biConnection->datasets()->withCount('syncLogs')->get();
        return response()->json(['success' => true, 'data' => $datasets]);
    }

    /**
     * 创建数据集
     */
    public function storeDataset(Request $request, BiConnection $biConnection)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'source_table' => 'required|in:licenses,customers,orders,invoices,subscriptions',
            'sync_frequency' => 'sometimes|in:manual,hourly,daily,weekly,monthly',
            'field_mapping' => 'nullable|array',
            'filters' => 'nullable|array',
        ]);

        $dataset = $biConnection->datasets()->create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
        ]));

        return response()->json(['success' => true, 'data' => $dataset], 201);
    }

    /**
     * 更新数据集
     */
    public function updateDataset(Request $request, BiDataset $biDataset)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'sync_frequency' => 'sometimes|in:manual,hourly,daily,weekly,monthly',
            'field_mapping' => 'nullable|array',
            'filters' => 'nullable|array',
        ]);

        $biDataset->update($validated);
        return response()->json(['success' => true, 'data' => $biDataset]);
    }

    /**
     * 删除数据集
     */
    public function destroyDataset(BiDataset $biDataset)
    {
        $biDataset->syncLogs()->delete();
        $biDataset->delete();
        return response()->json(['success' => true]);
    }

    /**
     * 执行同步
     */
    public function syncDataset(BiDataset $biDataset)
    {
        $log = $this->biExport->syncDataset($biDataset);
        return response()->json(['success' => true, 'data' => $log]);
    }

    /**
     * 同步日志
     */
    public function syncLogs(BiDataset $biDataset)
    {
        $logs = $biDataset->syncLogs()->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * 配置模板
     */
    public function configTemplate(string $platform)
    {
        return response()->json([
            'success' => true,
            'data' => ['fields' => BiExportService::getPlatformConfigTemplate($platform)],
        ]);
    }

    /**
     * 所有平台列表
     */
    public function platforms()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['key' => 'csv', 'name' => 'CSV 导出', 'icon' => '📈'],
            ],
        ]);
    }

    /**
     * 统计
     */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_connections' => BiConnection::count(),
                'total_datasets' => BiDataset::count(),
                'total_syncs' => \App\Models\BiSyncLog::count(),
                'recent_syncs' => \App\Models\BiSyncLog::whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
