<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AirGappedDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 气隙部署控制器 (Air-Gapped Deployment)
 *
 * 提供军工/政府/银行内网场景的离线管理 API
 *
 * @m3-61
 */
class AirGappedController extends Controller
{
    public function __construct(
        protected AirGappedDeploymentService $airGappedService,
    ) {}

    /**
     * 获取气隙部署状态
     */
    public function status(): JsonResponse
    {

        try {
            $status = $this->airGappedService->getStatus();

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Throwable $e) {
            Log::error('AirGapped status error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '获取状态失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取指标数据
     */
    public function metrics(): JsonResponse
    {

        try {
            $metrics = $this->airGappedService->getMetrics();

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '获取指标失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 扫描 U 盘 License 文件
     */
    public function scanUsb(): JsonResponse
    {
        try {
            $candidates = $this->airGappedService->scanUsbDrives();

            return response()->json([
                'success' => true,
                'data' => [
                    'candidates' => $candidates,
                    'count' => count($candidates),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '扫描 U 盘失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 导入 License 文件
     */
    public function importLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_path' => ['required', 'string'],
            'validate' => ['boolean'],
        ]);

        try {
            $result = $this->airGappedService->importLicense(
                $validated['file_path'],
                $validated['validate'] ?? true,
            );

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'License 导入失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上传 License 文件（通过 HTTP）
     */
    public function uploadLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_file' => ['required', 'file', 'mimes:lic,license,key,txt,bin', 'max:10240'],
        ]);

        try {
            $file = $request->file('license_file');
            $storedPath = $file->store('air-gapped/licenses');

            $result = $this->airGappedService->importLicense(
                storage_path('app/' . $storedPath),
                true,
            );

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'License 上传失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取已导入的 License 列表
     */
    public function listLicenses(): JsonResponse
    {
        $path = storage_path('app/air-gapped/licenses');
        $files = [];

        if (is_dir($path)) {
            $iterator = new \FilesystemIterator($path);
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }

        // 按修改时间降序
        usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));

        return response()->json([
            'success' => true,
            'data' => $files,
        ]);
    }

    /**
     * 获取 Docker 信息
     */
    public function dockerInfo(): JsonResponse
    {

        try {
            $info = $this->airGappedService->getDockerInfo();

            return response()->json([
                'success' => true,
                'data' => $info,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '获取 Docker 信息失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取离线更新包列表
     */
    public function listUpdates(): JsonResponse
    {
        $path = storage_path('app/air-gapped/updates');
        $packages = [];

        if (is_dir($path)) {
            $files = glob("{$path}/*.tar.gz");
            foreach ($files as $file) {
                $packages[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    /**
     * 应用离线更新包
     */
    public function applyUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package' => ['required', 'string'],
        ]);

        $packagePath = storage_path('app/air-gapped/updates/' . $validated['package']);

        try {
            $result = $this->airGappedService->applyUpdate($packagePath);

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上传离线更新包
     */
    public function uploadUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'update_package' => ['required', 'file', 'mimes:gz,tar,tgz', 'max:2097152'], // 2GB
        ]);

        try {
            $file = $request->file('update_package');
            $storedPath = $file->store('air-gapped/updates');

            return response()->json([
                'success' => true,
                'message' => '更新包上传成功',
                'path' => $storedPath,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 执行健康检查
     */
    public function healthCheck(): JsonResponse
    {
        $checks = [
            'php_version' => phpversion(),
            'extensions' => $this->airGappedService->getStatus()['php_extensions'],
            'storage' => $this->airGappedService->getStatus()['storage_writable'],
            'air_gapped_mode' => $this->airGappedService->isAirGappedMode(),
            'timestamp' => now()->toIso8601String(),
        ];

        $healthy = collect($checks['extensions'])->every(fn($v) => $v === true)
            && $checks['storage'] === true;

        return response()->json([
            'success' => true,
            'healthy' => $healthy,
            'data' => $checks,
        ]);
    }
}
