<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DependencyVulnerability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DependencySecurityController extends Controller
{
    /**
     * 漏洞列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = DependencyVulnerability::query();

        if ($request->filled('filter.ecosystem')) {
            $query->where('ecosystem', $request->input('filter.ecosystem'));
        }

        if ($request->filled('filter.severity')) {
            $query->where('severity', $request->input('filter.severity'));
        }

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        } else {
            // 默认只显示未修复的
            $query->whereIn('status', ['open', 'ignored']);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('package_name', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('cve', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $sortField = $request->input('sort', '-severity');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $allowedSorts = ['severity', 'package_name', 'detected_at', 'created_at'];
        if (in_array($field, $allowedSorts)) {
            if ($field === 'severity') {
                $query->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END {$direction}");
            } else {
                $query->orderBy($field, $direction);
            }
        } else {
            $query->latest('detected_at');
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 漏洞统计
     */
    public function stats(): JsonResponse
    {
        $cacheKey = 'deps_security_stats';
        $ttl = now()->addMinutes(5);

        $stats = Cache::remember($cacheKey, $ttl, function () {
            $open = DependencyVulnerability::where('status', 'open');

            return [
                'total_open' => (clone $open)->count(),
                'critical' => (clone $open)->where('severity', 'critical')->count(),
                'high' => (clone $open)->where('severity', 'high')->count(),
                'medium' => (clone $open)->where('severity', 'medium')->count(),
                'low' => (clone $open)->where('severity', 'low')->count(),
                'composer' => (clone $open)->where('ecosystem', 'composer')->count(),
                'npm' => (clone $open)->where('ecosystem', 'npm')->count(),
                'fixed_total' => DependencyVulnerability::where('status', 'fixed')->count(),
                'last_scan_at' => Cache::get('deps_last_scan_at'),
                'total_scanned_packages' => $this->countPackages(),
            ];
        });

        return ApiResponse::success($stats);
    }

    /**
     * 更新漏洞状态
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,fixed,ignored,false_positive',
            'note' => 'nullable|string|max:500',
        ]);

        $vuln = DependencyVulnerability::findOrFail($id);

        $vuln->status = $validated['status'];
        if ($validated['status'] === 'fixed') {
            $vuln->fixed_at = now();
        }
        if (! empty($validated['note'])) {
            $vuln->description = $validated['note'];
        }
        $vuln->save();

        Cache::forget('deps_security_stats');

        return ApiResponse::success($vuln->fresh(), __('app.dependency_security.vuln_status_updated'));
    }

    /**
     * 批量更新漏洞状态
     */
    public function batchUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|integer|exists:dependency_vulnerabilities,id',
            'status' => 'required|in:open,fixed,ignored,false_positive',
        ]);

        $count = DependencyVulnerability::whereIn('id', $validated['ids'])
            ->update([
                'status' => $validated['status'],
                'fixed_at' => $validated['status'] === 'fixed' ? now() : null,
            ]);

        Cache::forget('deps_security_stats');

        return ApiResponse::success(['updated' => $count], __("app.dependency_security.msg_60062fa5"));
    }

    /**
     * 触发手动扫描
     */
    public function triggerScan(): JsonResponse
    {
        // 异步执行扫描
        Artisan::queue('deps:scan', ['--notify' => true]);

        Cache::put('deps_last_scan_at', now()->toIso8601String(), now()->addDays(1));

        return ApiResponse::success(['started_at' => now()->toIso8601String()], __('app.dependency_security.scan_started_bg'));
    }

    /**
     * 获取扫描配置状态
     */
    public function config(): JsonResponse
    {
        $hasDependabot = file_exists(base_path('.github/dependabot.yml'));

        return ApiResponse::success([
            'dependabot_configured' => $hasDependabot,
            'last_scan_at' => Cache::get('deps_last_scan_at'),
            'ecosystems' => ['composer', 'npm'],
            'composer_version' => $this->getComposerVersion(),
            'node_version' => $this->getNodeVersion(),
        ]);
    }

    private function countPackages(): int
    {
        $count = 0;
        $composerPath = base_path('vendor/composer/installed.json');
        if (file_exists($composerPath)) {
            $data = json_decode(file_get_contents($composerPath), true);
            $count += count($data['packages'] ?? $data ?? []);
        }
        return $count;
    }

    private function getComposerVersion(): ?string
    {
        $output = shell_exec('composer --version 2>&1');
        if ($output && preg_match('/Composer version ([\d.]+)/', $output, $m)) {
            return $m[1];
        }
        return null;
    }

    private function getNodeVersion(): ?string
    {
        $output = shell_exec('node --version 2>&1');
        return $output ? trim($output) : null;
    }
}
