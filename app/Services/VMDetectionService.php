<?php

namespace App\Services;

use App\Models\Device;
use App\Support\DbSql;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 虚拟环境/模拟器检测服务 (M1.3-14)
 *
 * 通过多种检测方法识别设备是否运行在虚拟环境或模拟器中，
 * 支持 Docker/VMware/VirtualBox/KVM/Hyper-V/Xen/QEMU/Parallels/WSL/
 * Android 模拟器/iOS 模拟器/容器 等环境检测。
 */
class VMDetectionService
{
    // 虚拟机特征标识
    protected const VM_MAC_PREFIXES = [
        '00:05:69', // VMware
        '00:0c:29', // VMware
        '00:1c:14', // VMware
        '00:50:56', // VMware
        '00:15:5d', // Hyper-V
        '08:00:27', // VirtualBox
        '52:54:00', // QEMU/KVM
        '00:16:3e', // Xen
        '00:1c:42', // Parallels
    ];

    protected const VM_PCI_VENDORS = [
        '15ad' => 'VMware',
        '1ab8' => 'VirtualBox',
        '1af4' => 'QEMU/VirtIO',
        '5853' => 'XenSource',
    ];

    protected const VM_DMI_PATTERNS = [
        'virtualbox' => ['VirtualBox', 'vbox', 'Oracle'],
        'vmware' => ['VMware', 'VMW', 'Virtual Machine'],
        'qemu' => ['QEMU', 'qemu', 'KVM'],
        'xen' => ['Xen', 'XenSource'],
        'hyper-v' => ['Hyper-V', 'Virtual PC'],
        'kvm' => ['KVM', 'kvm'],
        'bochs' => ['Bochs', 'bochs'],
        'parallels' => ['Parallels', 'prl'],
    ];

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $cacheKey = 'vm_detection:dashboard';
        $ttl = 300;

        return Cache::remember($cacheKey, $ttl, function () {
            $totalDetected = Device::where('is_vm', true)->count();
            $totalChecked = Device::whereNotNull('vm_info')->count();
            $blockedCount = Device::where('is_vm', true)->where('trust_score', '<=', 10)->count();
            $recentDetections = Device::where('is_vm', true)
                ->where('updated_at', '>=', now()->subDays(7))
                ->count();

            // 按虚拟化类型统计
        $vmTypeExpr = DbSql::jsonExtract('vm_info', 'type');

        $typeStats = Device::where('is_vm', true)
            ->selectRaw("{$vmTypeExpr} as vm_type, COUNT(*) as total")
                ->groupBy('vm_type')
                ->get()
                ->pluck('total', 'vm_type')
                ->toArray();

            return [
                'total_detected' => $totalDetected,
                'total_checked' => $totalChecked,
                'blocked_count' => $blockedCount,
                'recent_detections_7d' => $recentDetections,
                'type_stats' => $typeStats,
                'strategy' => config('vm-detection.strategy'),
                'enabled' => config('vm-detection.enabled'),
            ];
        });
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget('vm_detection:dashboard');
    }

    /**
     * 对设备执行虚拟环境检测
     */
    public function detect(Device $device): array
    {
        $enabled = config('vm-detection.enabled', true);
        if (!$enabled) {
            return ['is_vm' => false, 'confidence' => 0, 'reason' => '检测已禁用'];
        }

        $checks = config('vm-detection.checks', []);
        $threshold = config('vm-detection.detection_threshold', 2);
        $findings = [];
        $totalScore = 0;

        // 检测方法 1: MAC 地址前缀
        if ($checks['vmware'] ?? true) {
            $macResult = $this->checkMacAddress($device);
            if ($macResult) {
                $findings[] = $macResult;
                $totalScore += $macResult['weight'];
            }
        }

        // 检测方法 2: DMI/SMBIOS 信息
        if ($device->dmi_info) {
            $dmiResult = $this->checkDmiInfo($device->dmi_info);
            if ($dmiResult) {
                $findings[] = $dmiResult;
                $totalScore += $dmiResult['weight'];
            }
        }

        // 检测方法 3: CPU 特征
        if ($device->cpu_info) {
            $cpuResult = $this->checkCpuInfo($device->cpu_info);
            if ($cpuResult) {
                $findings[] = $cpuResult;
                $totalScore += $cpuResult['weight'];
            }
        }

        // 检测方法 4: 设备名称特征
        $nameResult = $this->checkDeviceName($device);
        if ($nameResult) {
            $findings[] = $nameResult;
            $totalScore += $nameResult['weight'];
        }

        $isVm = count($findings) >= $threshold;
        $confidence = min(100, $totalScore);
        $vmType = $isVm ? ($findings[0]['vm_type'] ?? 'unknown') : null;

        // 更新设备记录
        $device->is_vm = $isVm;
        $device->vm_info = $isVm ? [
            'type' => $vmType,
            'confidence' => $confidence,
            'findings' => $findings,
            'detected_at' => now()->toIso8601String(),
        ] : null;

        if ($isVm && config('vm-detection.strategy') === 'reduce_trust') {
            $device->trust_score = min($device->trust_score ?? 50, config('vm-detection.vm_trust_score', 20));
        }

        $device->save();

        // 日志记录
        if ($isVm && config('vm-detection.monitoring.log_detections')) {
            Log::warning('虚拟环境检测命中', [
                'device_id' => $device->id,
                'vm_type' => $vmType,
                'confidence' => $confidence,
                'findings' => $findings,
            ]);
        }

        return [
            'is_vm' => $isVm,
            'vm_type' => $vmType,
            'confidence' => $confidence,
            'findings' => $findings,
            'strategy_applied' => $isVm ? config('vm-detection.strategy') : null,
        ];
    }

    /**
     * 检查 MAC 地址前缀
     */
    protected function checkMacAddress(Device $device): ?array
    {
        $mac = strtoupper(str_replace('-', ':', $device->mac_address ?? ''));
        if (!$mac) return null;

        foreach (self::VM_MAC_PREFIXES as $prefix) {
            if (str_starts_with($mac, $prefix)) {
                $vendor = match (true) {
                    str_starts_with($prefix, '00:05:69') ||
                    str_starts_with($prefix, '00:0c:29') ||
                    str_starts_with($prefix, '00:1c:14') ||
                    str_starts_with($prefix, '00:50:56') => 'VMware',
                    str_starts_with($prefix, '00:15:5d') => 'Hyper-V',
                    str_starts_with($prefix, '08:00:27') => 'VirtualBox',
                    str_starts_with($prefix, '52:54:00') => 'QEMU/KVM',
                    str_starts_with($prefix, '00:16:3e') => 'Xen',
                    str_starts_with($prefix, '00:1c:42') => 'Parallels',
                    default => 'Unknown',
                };
                return [
                    'check' => 'mac_prefix',
                    'match' => $prefix,
                    'vm_type' => strtolower($vendor),
                    'weight' => 30,
                    'detail' => "MAC地址前缀 {$prefix} 属于 {$vendor}",
                ];
            }
        }
        return null;
    }

    /**
     * 检查 DMI 信息
     */
    protected function checkDmiInfo(?array $dmiInfo): ?array
    {
        if (!$dmiInfo) return null;

        $dmiString = json_encode($dmiInfo);
        foreach (self::VM_DMI_PATTERNS as $vmType => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($dmiString, $pattern) !== false) {
                    return [
                        'check' => 'dmi_info',
                        'match' => $pattern,
                        'vm_type' => $vmType,
                        'weight' => 35,
                        'detail' => "DMI/SMBIOS 信息包含特征 '{$pattern}'",
                    ];
                }
            }
        }
        return null;
    }

    /**
     * 检查 CPU 信息
     */
    protected function checkCpuInfo(?array $cpuInfo): ?array
    {
        if (!$cpuInfo) return null;

        $cpuString = json_encode($cpuInfo);

        $cpuPatterns = [
            'qemu' => ['QEMU Virtual CPU', 'Common KVM processor'],
            'vmware' => ['VMware Virtual CPU'],
            'hyper-v' => ['Hyper-V'],
            'virtualbox' => ['VirtualBox'],
            'kvm' => ['KVM'],
        ];

        foreach ($cpuPatterns as $vmType => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($cpuString, $pattern) !== false) {
                    return [
                        'check' => 'cpu_info',
                        'match' => $pattern,
                        'vm_type' => $vmType,
                        'weight' => 40,
                        'detail' => "CPU 信息包含特征 '{$pattern}'",
                    ];
                }
            }
        }
        return null;
    }

    /**
     * 检查设备名称
     */
    protected function checkDeviceName(Device $device): ?array
    {
        $name = $device->name ?? $device->hostname ?? '';
        if (!$name) return null;

        $patterns = [
            'docker' => ['docker', 'container'],
            'android_emulator' => ['sdk_build', 'generic_', 'emulator', 'android_emulator'],
            'ios_simulator' => ['simulator', 'iphone_simulator'],
            'wsl' => ['wsl', 'microsoft-wsl', 'linux-foundation'],
        ];

        foreach ($patterns as $vmType => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($name, $keyword) !== false) {
                    return [
                        'check' => 'device_name',
                        'match' => $keyword,
                        'vm_type' => $vmType,
                        'weight' => 20,
                        'detail' => "设备名称包含特征 '{$keyword}'",
                    ];
                }
            }
        }
        return null;
    }

    /**
     * 分页查询虚拟环境设备列表
     */
    public function getDetectedDevices(array $params = []): array
    {
        $query = Device::where('is_vm', true)
            ->orderByDesc('updated_at');

        if (!empty($params['vm_type'])) {
            $query->where('vm_info->type', $params['vm_type']);
        }
        if (!empty($params['search'])) {
            $s = $params['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('fingerprint', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        $page = (int) ($params['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取配置
     */
    public function getConfig(): array
    {
        return [
            'enabled' => config('vm-detection.enabled'),
            'strategy' => config('vm-detection.strategy'),
            'vm_trust_score' => config('vm-detection.vm_trust_score'),
            'detection_threshold' => config('vm-detection.detection_threshold'),
            'checks' => config('vm-detection.checks'),
        ];
    }

    /**
     * 更新配置
     */
    public function updateConfig(array $data): void
    {
        // 仅运行时生效，生产环境应写入 .env 或数据库配置表
        config(['vm-detection.enabled' => $data['enabled'] ?? config('vm-detection.enabled')]);
        config(['vm-detection.strategy' => $data['strategy'] ?? config('vm-detection.strategy')]);
        config(['vm-detection.vm_trust_score' => $data['vm_trust_score'] ?? config('vm-detection.vm_trust_score')]);
        config(['vm-detection.detection_threshold' => $data['detection_threshold'] ?? config('vm-detection.detection_threshold')]);

        $this->clearCache();
    }
}
